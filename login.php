<?php
// ไฟล์: pdhbed/login.php
session_start();
require_once 'api/db_connect_pdo.php';

if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true){
    header("Location: index.php");
    exit;
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if(!empty($username) && !empty($password)){
        try {
            $pdo = get_connection('app', 'test');
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if($user && password_verify($password, $user['password'])){
                
                // 🛑 ตรวจสอบสถานะการอนุมัติ (Status) 🛑
                if ($user['status'] === 'pending') {
                    $error = "บัญชีของคุณอยู่ระหว่างรอการอนุมัติจาก Admin ครับ";
                } elseif ($user['status'] === 'rejected') {
                    $error = "บัญชีนี้ถูกระงับการใช้งาน ติดต่อผู้ดูแลระบบ";
                } else {
                    // Approved แล้ว เข้าใช้งานได้!
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['fullname']  = $user['fullname'];
                    $_SESSION['role']      = $user['role'];

                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);

                    header("Location: index.php");
                    exit;
                }
            } else {
                $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
        } catch (Exception $e) {
            $error = "ระบบฐานข้อมูลมีปัญหา: " . $e->getMessage();
        }
    } else {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>PDHBed Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family:'Sarabun',sans-serif; background: linear-gradient(135deg,#dbeafe,#eff6ff); }</style>
</head>
<body class="min-h-screen flex items-center justify-center">

<div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-8">
            <div class="text-5xl mb-3"><i class="fa-solid fa-hospital text-blue-600"></i></div>
            <h1 class="text-3xl font-bold text-blue-700">PDHBed</h1>
            <p class="text-gray-500 mt-2">ระบบติดตามผู้ป่วยใน & จัดการมัดจำ</p>
        </div>

        <?php if($error != ""){ ?>
        <div class="mb-4 bg-red-50 text-red-700 border border-red-200 rounded-lg p-3 text-center text-sm font-bold">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?>
        </div>
        <?php } ?>

        <form method="post" action="">
            <div class="mb-5">
                <label class="block text-sm font-bold text-gray-700 mb-2"><i class="fa-solid fa-user"></i> Username</label>
                <input type="text" name="username" required autocomplete="off" class="w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none bg-gray-50" />
            </div>
            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 mb-2"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none bg-gray-50" />
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-lg hover:shadow-xl">
                เข้าสู่ระบบ <i class="fa-solid fa-arrow-right-to-bracket ml-1"></i>
            </button>
        </form>
        
        <div class="text-center mt-5 text-sm">
            ยังไม่มีบัญชี? <a href="register.php" class="text-purple-600 font-bold hover:underline">ลงทะเบียนขอสิทธิ์</a>
        </div>
    </div>
</div>

</body>
</html>