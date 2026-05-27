<?php
// ไฟล์: pdhbed/api/manage_deposit.php
session_start(); 
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require 'db_connect_pdo.php';

try {
    $pdo = get_connection('app', 'test');
    $action = $_REQUEST['action'] ?? '';
    
    // ดึง Fullname มาใช้ ถ้าไม่มีให้ใช้ Username แทน
    $user = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Unknown';
    $user_role = strtolower(trim($_SESSION['role'] ?? 'user'));

    function table_exists($pdo, $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    function column_exists($pdo, $table, $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    function ensure_deposit_note_column($pdo) {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        if (!column_exists($pdo, 'custom_deposits', 'note')) {
            try {
                $pdo->exec("ALTER TABLE custom_deposits ADD COLUMN note TEXT NULL AFTER amount");
            } catch (Exception $e) {
                throw new Exception("ยังไม่ได้เพิ่มคอลัมน์ custom_deposits.note กรุณารัน SQL migration ด้วย user ที่มีสิทธิ์ ALTER ก่อนใช้งาน");
            }
        }
    }

    function ensure_deposit_due_notes_table($pdo) {
        if (table_exists($pdo, 'deposit_due_notes')) {
            return;
        }

        try {
            $pdo->exec("CREATE TABLE deposit_due_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                an VARCHAR(20) NOT NULL,
                hn VARCHAR(20) DEFAULT NULL,
                pt_name VARCHAR(255) DEFAULT NULL,
                note TEXT NULL,
                updated_by VARCHAR(100) DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_deposit_due_note_an (an)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {
            throw new Exception("ยังไม่ได้สร้างตาราง deposit_due_notes กรุณารัน SQL migration ด้วย user ที่มีสิทธิ์ CREATE ก่อนใช้งาน");
        }
    }

    function sync_deposit_due_note($pdo, $hn, $an, $name, $note, $user) {
        $note = trim((string)$note);
        if ($an === '' || $note === '') {
            return;
        }

        ensure_deposit_due_notes_table($pdo);
        $sql = "INSERT INTO deposit_due_notes (an, hn, pt_name, note, updated_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    hn = VALUES(hn),
                    pt_name = VALUES(pt_name),
                    note = VALUES(note),
                    updated_by = VALUES(updated_by)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$an, $hn, $name, $note, $user]);
    }

    ensure_deposit_note_column($pdo);
    ensure_deposit_due_notes_table($pdo);

    // --- 1. บันทึก / แก้ไข ข้อมูล ---
    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $hn = $_POST['hn'];
        $an = $_POST['an'];
        $name = $_POST['name']; 
        $receipt_date = $_POST['receipt_date'];
        $receipt_no = $_POST['receipt_no'];
        $item_desc = $_POST['item_desc'];
        $amount = floatval($_POST['amount']);
        $note = trim($_POST['note'] ?? '');

        if(empty($id)) {
            $sql = "INSERT INTO custom_deposits (hn, an, pt_name, receipt_date, receipt_no, item_desc, amount, note, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hn, $an, $name, $receipt_date, $receipt_no, $item_desc, $amount, $note, $user]);
        } else {
            $sql = "UPDATE custom_deposits SET receipt_date=?, receipt_no=?, item_desc=?, amount=?, note=?, recorded_by=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$receipt_date, $receipt_no, $item_desc, $amount, $note, $user, $id]);
        }
        sync_deposit_due_note($pdo, $hn, $an, $name, $note, $user);
        echo json_encode(['status' => 'success']);

    } 
    // --- 2. ลบข้อมูล พร้อมเก็บ Log (เฉพาะ Admin) ---
    elseif ($action === 'delete') {
        if ($user_role !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'ปฏิเสธการเข้าถึง: สิทธิ์ของคุณไม่สามารถลบข้อมูลได้']);
            exit;
        }

        $id = $_POST['id'];
        $reason = trim($_POST['reason']);

        if (empty($reason)) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุเหตุผลในการลบ']);
            exit;
        }

        // 2.1 ดึงข้อมูลเดิมออกมาก่อน
        $stmt = $pdo->prepare("SELECT * FROM custom_deposits WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            // 2.2 บันทึกลงตาราง Log (เก็บประวัติ)
            $sqlLog = "INSERT INTO deposit_delete_logs 
                      (original_id, hn, an, pt_name, receipt_date, receipt_no, item_desc, amount, recorded_by, deleted_by, delete_reason) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtLog = $pdo->prepare($sqlLog);
            $stmtLog->execute([
                $row['id'], $row['hn'], $row['an'], $row['pt_name'], 
                $row['receipt_date'], $row['receipt_no'], $row['item_desc'], 
                $row['amount'], $row['recorded_by'], $user, $reason
            ]);

            // 2.3 ลบข้อมูลจริงออกจากตารางหลัก
            $stmtDel = $pdo->prepare("DELETE FROM custom_deposits WHERE id = ?");
            $stmtDel->execute([$id]);

            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลที่ต้องการลบ']);
        }
    } 
    // --- 3. ดึงประวัติข้อมูลมาแสดง ---
    elseif ($action === 'get') {
        $an = $_GET['an'];
        $sql = "SELECT * FROM custom_deposits WHERE an = ? ORDER BY receipt_date DESC, created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$an]);
        
        $data = [];
        $total_deposit = 0;
        
        while($row = $stmt->fetch()) {
            $data[] = [
                'id' => $row['id'],
                'receipt_date' => $row['receipt_date'],
                'date_show' => date('d/m/Y', strtotime($row['receipt_date'])),
                'time_show' => date('H:i', strtotime($row['created_at'])),
                'receipt_no' => $row['receipt_no'],
                'item_desc' => $row['item_desc'],
                'amount' => number_format($row['amount'], 2),
                'amount_raw' => $row['amount'],
                'note' => $row['note'] ?? '',
                'recorded_by' => $row['recorded_by'] ?: '-' 
            ];
            $total_deposit += floatval($row['amount']);
        }

        $stmtNote = $pdo->prepare("SELECT note, updated_by, updated_at FROM deposit_due_notes WHERE an = ? LIMIT 1");
        $stmtNote->execute([$an]);
        $dueNote = $stmtNote->fetch() ?: ['note' => '', 'updated_by' => '', 'updated_at' => ''];

        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'total' => number_format($total_deposit, 2),
            'due_note' => [
                'note' => $dueNote['note'] ?? '',
                'updated_by' => $dueNote['updated_by'] ?? '',
                'updated_at' => $dueNote['updated_at'] ?? ''
            ]
        ]);
    } 
    // --- 4. จัดการ Template รายการ (Items) ---
    elseif ($action === 'get_items') {
        $stmt = $pdo->query("SELECT * FROM deposit_items ORDER BY item_name ASC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    } elseif ($action === 'save_item') {
        $stmt = $pdo->prepare("INSERT INTO deposit_items (item_name) VALUES (?)");
        $stmt->execute([$_POST['item_name']]);
        echo json_encode(['status' => 'success']);
    } elseif ($action === 'delete_item') {
        $stmt = $pdo->prepare("DELETE FROM deposit_items WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
