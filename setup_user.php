<?php
// ไฟล์สำหรับสร้าง User คนแรก (รันเสร็จแล้วควรลบไฟล์นี้ทิ้ง)
require 'api/db_connect_pdo.php';

try {
    $pdo = get_connection('app', 'test');
    
    // ตั้งค่า User แรก
    $username = 'admin';
    $password = 'pdh10832'; // รหัสผ่านที่คุณต้องการ
    $fullname = 'ผู้ดูแลระบบ (Admin)';
    $role = 'admin';
    
    // เข้ารหัสรหัสผ่าน (สไตล์มืออาชีพ)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // บันทึกลงฐานข้อมูล
    $sql = "INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $hashed_password, $fullname, $role]);
    
    echo "<h2 style='color: green;'>✅ สร้างผู้ใช้งาน '$username' สำเร็จ! รหัสผ่านถูกเข้ารหัสเรียบร้อยแล้ว</h2>";
    echo "<p>คุณสามารถไปที่หน้า <a href='login.php'>Login</a> ได้เลย (และอย่าลืมลบไฟล์ setup_user.php นี้ทิ้งด้วยนะครับ)</p>";

} catch (Exception $e) {
    if ($e->getCode() == 23000) {
        echo "<h2 style='color: orange;'>⚠️ มี User นี้ในระบบแล้วครับ ไม่สามารถสร้างซ้ำได้</h2>";
    } else {
        echo "<h2 style='color: red;'>❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "</h2>";
    }
}
?>