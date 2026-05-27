<?php
// ไฟล์: pdhbed/api/test_connection.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🚑 เริ่มทดสอบการเชื่อมต่อฐานข้อมูล...</h2>";

// 1. ตรวจสอบว่ามีไฟล์ config ไหม
if (file_exists('../config/db.php')) {
    echo "✅ พบไฟล์ config/db.php<br>";
    require '../config/db.php';
} else {
    die("❌ ไม่พบไฟล์ config/db.php (ตรวจสอบตำแหน่งไฟล์)");
}

// 2. ทดสอบเชื่อมต่อ
try {
    if ($pdo) {
        echo "✅ เชื่อมต่อฐานข้อมูลสำเร็จ (Object PDO ทำงาน)<br>";
    }
} catch (Exception $e) {
    die("❌ เชื่อมต่อล้มเหลว: " . $e->getMessage());
}

// 3. ทดสอบดึงข้อมูลภาษาไทย
$pdo->exec("SET NAMES tis620");
echo "✅ ตั้งค่า TIS-620 สำเร็จ<br>";

// 4. ลองดึงข้อมูลจริง 1 แถว (เปลี่ยน SQL ให้ง่ายที่สุด)
echo "🔍 กำลังลองดึงข้อมูล...<br>";
try {
    // ลองดึงจากตารางหลักก่อน
    $sql = "SELECT an FROM ipd.ipd LIMIT 1"; 
    $stmt = $pdo->query($sql);
    
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "🎉 <b>สำเร็จ!</b> พบข้อมูล AN ตัวอย่าง: " . $row['an'];
    } else {
        echo "❌ Query ไม่ผ่าน (อาจจะชื่อตารางผิด): ";
        print_r($pdo->errorInfo());
    }
} catch (Exception $e) {
    echo "❌ เกิดข้อผิดพลาดขณะ Query: " . $e->getMessage();
}
?>