<?php
// ไฟล์: pdhbed/api/check_updates.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require '../config/db.php';

try {
    // [FIX] เพิ่ม OR i.datedsc = CURDATE() ให้ตรงกับ get_patients.php
    $sql = "SELECT i.an, i.hn, i.now_ward, r.roomname, i.datedsc 
            FROM ipd.ipd i 
            LEFT JOIN hos.roomno r ON i.now_ward = r.roomcode
            WHERE (i.datedsc IS NULL OR i.datedsc = '0000-00-00' OR i.datedsc = '' OR i.datedsc = CURDATE())";
    
    $stmt = $pdo->query($sql);
    $data = [];
    
    while ($row = $stmt->fetch()) {
        $wardName = !empty($row['roomname']) ? $row['roomname'] : $row['now_ward'];
        if (!mb_detect_encoding($wardName, 'utf-8', true)) {
            $wardName = iconv("tis-620", "utf-8//IGNORE", $wardName);
        }

        $data[] = [
            'an' => $row['an'],
            'ward' => $wardName,
            // ส่งค่า datedsc ไปด้วยถ้าจำเป็น
            'is_dsc' => ($row['datedsc'] != '' && $row['datedsc'] != '0000-00-00')
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>