<?php
// ไฟล์: pdhbed/api/get_patients.php (เวอร์ชันสมบูรณ์: แสดงยอดมัดจำ + รายละเอียดค่าใช้จ่ายครบถ้วน)
error_reporting(0); 
ini_set('display_errors', 0);

header('Content-Type: application/json');
require '../config/db.php'; // เชื่อมต่อ HIS (Server 251)
require 'db_connect_pdo.php'; // สำหรับเชื่อมต่อ Server 240
require __DIR__ . '/cost_helpers.php';

function utf8_converter($array) {
    array_walk_recursive($array, function(&$item, $key) {
        if ($item === null) return;
        $str = (string)$item; 
        if (!mb_detect_encoding($str, 'utf-8', true)) {
            $item = iconv("tis-620", "utf-8//IGNORE", $str);
        }
    });
    return $array;
}

try {
    // 1. ดึงข้อมูลผู้ป่วยจาก HIS
    $sql = "SELECT 
                i.an, i.hn, i.regdate, i.frequency, i.opd_date, i.dateadm, i.datedsc, i.bed, i.now_ward, i.sendward,
                o.fullname as name, o.cardid, o.ptclass as right_code,
                ins.Name as right_name, r.roomname as ward_name, r2.roomname as sendward_name,
                COALESCE(
                    NULLIF((SELECT SUM(totalprice) FROM hos_bill.billipd WHERE an = i.an), 0),
                    NULLIF((SELECT SUM(price) FROM hos_bill.billipdlist WHERE an = i.an), 0),
                    0
                ) AS bill_total
            FROM ipd.ipd i 
            LEFT JOIN opd.opd o ON i.hn = o.hn AND i.regdate = o.regdate AND i.frequency = o.frequency
            LEFT JOIN hos.insclasses ins ON o.ptclass = ins.code
            LEFT JOIN hos.roomno r ON i.now_ward = r.roomcode
            LEFT JOIN hos.roomno r2 ON i.sendward = r2.roomcode
            WHERE (i.datedsc IS NULL OR i.datedsc = '0000-00-00' OR i.datedsc = '' OR i.datedsc = CURDATE()) 
            ORDER BY i.datedsc ASC, i.bed ASC";

    $stmt = $pdo->query($sql);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $costs = [];
    $deposits = []; // ตัวแปรเก็บยอดมัดจำ

    if (count($patients) > 0) {
        $ans = array_column($patients, 'an');
        $in_clause = "'" . implode("','", $ans) . "'";

        // 2. ดึงค่าใช้จ่ายจาก HIS รวมรายการ OPD ค้างชำระ/แล็บที่มาก่อน admit
        $costs = load_patient_costs($pdo, $patients);

        // 3. ดึงยอดมัดจำจาก Server 240 (ฐานข้อมูล test)
        try {
            $pdoApp = get_connection('app', 'test');
            $qDep = "SELECT an, SUM(amount) as total_dep FROM custom_deposits WHERE an IN ($in_clause) GROUP BY an";
            $stDep = $pdoApp->query($qDep);
            while ($r = $stDep->fetch()) {
                $deposits[$r['an']] = floatval($r['total_dep']);
            }
        } catch (Exception $e) {
            // ถ้าต่อ Server 240 ไม่ได้ ก็ข้ามไป (ไม่ให้เว็บหลักพัง)
        }
    }

    $data = [];
    
    // 4. วนลูปประกอบร่างข้อมูล
    foreach ($patients as $row) {
        $an = $row['an'];
        $regDateThai = '-';
        $los = 0;
        
        if (!empty($row['regdate']) && $row['regdate'] != '0000-00-00') {
            $y = date('Y', strtotime($row['regdate'])) + 543;
            $m = date('m', strtotime($row['regdate']));
            $d = date('d', strtotime($row['regdate']));
            $regDateThai = "$d/$m/$y";
            $diff = abs(strtotime(date('Y-m-d')) - strtotime($row['regdate']));
            $los = floor($diff / (60 * 60 * 24));
        }

        $is_dsc = ($row['datedsc'] != '' && $row['datedsc'] != '0000-00-00');
        $name = !empty($row['name']) ? $row['name'] : 'ไม่พบชื่อ';
        $wardName = !empty($row['ward_name']) ? $row['ward_name'] : $row['now_ward'];
        $rightDisplay = !empty($row['right_name']) ? $row['right_name'] : $row['right_code'];
        
        $old_ward = ""; $is_moved = false; $move_history = ''; 
        if ($row['sendward'] != '' && $row['sendward'] != $row['now_ward']) {
            $old_ward = !empty($row['sendward_name']) ? $row['sendward_name'] : $row['sendward'];
            $move_history = "ย้ายมาจาก: " . $old_ward;
            $is_moved = true;
        }

        // --- เตรียมยอดเงินแยกหมวดหมู่ ---
        $amt_drug  = isset($costs[$an]['drug']) ? $costs[$an]['drug'] : 0;
        $amt_lab   = isset($costs[$an]['lab']) ? $costs[$an]['lab'] : 0;
        $amt_xray  = isset($costs[$an]['xray']) ? $costs[$an]['xray'] : 0;
        $amt_other = isset($costs[$an]['other']) ? $costs[$an]['other'] : 0;
        $orderTotal = $amt_drug + $amt_lab + $amt_xray + $amt_other;
        $billTotal = (float)($row['bill_total'] ?? 0);
        $totalAmt  = $billTotal > 0 ? $billTotal : $orderTotal;
        $costSource = $billTotal > 0 ? 'bill' : 'order';

        // เตรียมยอดมัดจำ
        $deposit_amt = isset($deposits[$an]) ? $deposits[$an] : 0;

        $data[] = [
            'an'            => $an,
            'hn'            => $row['hn'],
            'cardid'        => $row['cardid'],
            'bed'           => $row['bed'],
            'name'          => $name,
            'ward'          => $wardName,
            'is_dsc'        => $is_dsc, 
            'move_history'  => $move_history, 
            'is_moved'      => $is_moved,
            'old_ward'      => $old_ward,
            'right'         => $rightDisplay,
            'regdate'       => $row['regdate'],
            'regdate_thai'  => $regDateThai,
            'los'           => $los,
            
            // 🌟 เพิ่มการส่งยอดเงินย่อย (Breakdown) กลับไปให้ Modal ใช้แสดงผล
            'amt_drug'      => number_format($amt_drug, 2),
            'amt_lab'       => number_format($amt_lab, 2),
            'amt_xray'      => number_format($amt_xray, 2),
            'amt_other'     => number_format($amt_other, 2),
            
            'total_amt'     => $totalAmt,
            'total_amt_fmt' => number_format($totalAmt, 2),
            'cost_source'   => $costSource,
            'order_total'   => $orderTotal,
            'order_total_fmt' => number_format($orderTotal, 2),
            'bill_total'    => $billTotal,
            'bill_total_fmt' => number_format($billTotal, 2),
            'deposit_amt'   => $deposit_amt,
            'deposit_fmt'   => number_format($deposit_amt, 2)
        ];
    }

    $data = utf8_converter($data);
    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
