<?php
// ไฟล์: pdhbed/api/debug_error.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🕵️‍♂️ กำลังตรวจสอบคำสั่ง SQL...</h2>";

if (!file_exists('../config/db.php')) { die("❌ ไม่พบไฟล์ config"); }
require '../config/db.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES tis620");

    // นี่คือ SQL ชุดเดียวกับที่ใช้ใน get_patients.php
    $sql = "SELECT 
            i.an, i.hn
            FROM ipd.ipd i 
            LEFT JOIN opd.opd o ON i.hn = o.hn AND i.regdate = o.regdate
            LEFT JOIN hos.insclasses ins ON o.ptclass = ins.code
            LEFT JOIN hos.roomno r ON i.now_ward = r.roomcode
            LEFT JOIN hos.roomno r2 ON i.sendward = r2.roomcode
            WHERE (i.datedsc IS NULL OR i.datedsc = '0000-00-00' OR i.datedsc = '') 
            LIMIT 1";

    echo "กำลังรัน Query...<br>";
    $stmt = $pdo->query($sql);
    echo "<h3 style='color: green;'>✅ Query ทำงานสำเร็จ! (โค้ดถูกต้องแล้ว)</h3>";
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "ตัวอย่างข้อมูล: <pre>" . print_r($row, true) . "</pre>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ พบจุดผิดพลาด (SQL Error):</h3>";
    echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid red; border-radius: 5px;'>";
    echo "<strong>Error Message:</strong> " . $e->getMessage();
    echo "</div>";
    
    echo "<h4>วิเคราะห์เบื้องต้น:</h4>";
    $msg = $e->getMessage();
    if (strpos($msg, "Table") !== false && strpos($msg, "doesn't exist") !== false) {
        echo "⚠️ <b>สาเหตุ:</b> คุณใส่ชื่อตารางผิดครับ ระบบหาตารางไม่เจอ<br>";
        echo "ให้ดูใน Error Message ว่าตารางไหนที่มันฟ้องว่า <b>doesn't exist</b>";
    } else {
        echo "⚠️ เป็น Error เกี่ยวกับข้อมูล หรือ Syntax SQL";
    }
}
?>