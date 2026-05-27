<?php
// ไฟล์: pdhbed/register.php
session_start();
require_once 'api/db_connect_pdo.php';

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');

    if (!empty($username) && !empty($password) && !empty($fullname)) {
        try {
            $pdo = get_connection('app', 'test');
            
            // เช็คว่า Username ซ้ำไหม
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->rowCount() > 0) {
                $message = "ชื่อผู้ใช้นี้ (Username) ถูกใช้ไปแล้ว กรุณาใช้ชื่ออื่น";
                $msgType = "error";
            } else {
                // เข้ารหัสรหัสผ่าน และเพิ่มข้อมูล (ค่าเริ่มต้น role = viewer, status = pending)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, password, fullname, role, status) VALUES (?, ?, ?, 'viewer', 'pending')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $hashed_password, $fullname]);
                
                $message = "ลงทะเบียนสำเร็จ! กรุณารอผู้ดูแลระบบ (Admin) อนุมัติการเข้าใช้งาน";
                $msgType = "success";
            }
        } catch (Exception $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $msgType = "error";
        }
    } else {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วน";
        $msgType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ลงทะเบียนเข้าใช้งาน - PDHBed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family:'Sarabun',sans-serif; background: linear-gradient(135deg,#fdf4ff,#e0e7ff); }</style>
</head>
<body class="min-h-screen flex items-center justify-center">

<div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2"><i class="fa-solid fa-user-plus text-purple-600"></i></div>
            <h1 class="text-2xl font-bold text-purple-700">ลงทะเบียนผู้ใช้ใหม่</h1>
            <p class="text-gray-500 text-sm mt-1">ระบบติดตามผู้ป่วยใน PDHBed</p>
        </div>

        <?php if($message != ""): ?>
            <div class="mb-4 p-3 text-center text-sm font-bold rounded-lg border <?php echo $msgType == 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'; ?>">
                <?php echo $message; ?>
            </div>
            <?php if($msgType == 'success') echo '<div class="text-center"><a href="login.php" class="text-blue-600 underline font-bold">กลับไปหน้า Login</a></div>'; ?>
        <?php endif; ?>

        <?php if($msgType != 'success'): ?>
        <form method="post" action="">
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">ชื่อ-สกุล (ภาษาไทย)</label>
                <input type="text" name="fullname" required autocomplete="off" placeholder="เช่น พยาบาลวิชาชีพ เอบีซี" class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-purple-500 outline-none bg-gray-50" />
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Username (สำหรับ Log in)</label>
                <input type="text" name="username" required autocomplete="off" placeholder="ภาษาอังกฤษหรือตัวเลข" class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-purple-500 outline-none bg-gray-50" />
            </div>
            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-purple-500 outline-none bg-gray-50" />
            </div>
            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-xl transition shadow-lg">
                ลงทะเบียนขอสิทธิ์ <i class="fa-solid fa-paper-plane ml-1"></i>
            </button>
        </form>
        <div class="text-center mt-5 text-sm">
            มีบัญชีอยู่แล้ว? <a href="login.php" class="text-blue-600 font-bold hover:underline">เข้าสู่ระบบที่นี่</a>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>