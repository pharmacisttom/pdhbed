<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect_pdo.php';

function send_json($payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function table_exists($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
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

try {
    if (!isset($_SESSION['logged_in'])) {
        send_json(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $an = trim($_POST['an'] ?? '');
    $hn = trim($_POST['hn'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $user = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Unknown';

    if ($an === '') {
        send_json(['status' => 'error', 'message' => 'ไม่พบ AN สำหรับบันทึกหมายเหตุ']);
        exit;
    }

    $pdo = get_connection('app', 'test');
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

    send_json(['status' => 'success']);
} catch (Exception $e) {
    send_json(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
