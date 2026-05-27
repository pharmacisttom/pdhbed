<?php
// ไฟล์: pdhbed/admin_users.php
session_start();
require_once 'api/db_connect_pdo.php';

// ป้องกันคนที่ไม่ใช่ Admin เข้าหน้านี้
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    die("<h1>❌ Access Denied: สำหรับผู้ดูแลระบบเท่านั้น</h1><a href='index.php'>กลับหน้าหลัก</a>");
}

$pdo = get_connection('app', 'test');

// ประมวลผลเมื่อมีการกดปุ่มอนุมัติ หรือ ระงับ หรือ เปลี่ยน Role
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $user_id = $_POST['user_id'];
    
    if ($_POST['action'] == 'approve') {
        $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?")->execute([$user_id]);
    } elseif ($_POST['action'] == 'reject') {
        $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")->execute([$user_id]);
    } elseif ($_POST['action'] == 'change_role') {
        $new_role = $_POST['new_role'];
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $user_id]);
    }
    header("Location: admin_users.php"); // รีเฟรชหน้า
    exit;
}

// ดึงผู้ใช้งานทั้งหมด
$users = $pdo->query("SELECT * FROM users ORDER BY status ASC, id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family:'Sarabun',sans-serif; background-color: #f3f4f6; }</style>
</head>
<body>

<div class="max-w-6xl mx-auto py-10 px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-users-gear text-blue-600"></i> จัดการระบบผู้ใช้งาน (Admin Panel)</h1>
        <a href="index.php" class="bg-gray-200 px-4 py-2 rounded font-bold hover:bg-gray-300">กลับหน้าแรก</a>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="p-3">ID</th>
                    <th class="p-3">Username</th>
                    <th class="p-3">ชื่อ-สกุล</th>
                    <th class="p-3 text-center">สิทธิ์ (Role)</th>
                    <th class="p-3 text-center">สถานะ (Status)</th>
                    <th class="p-3">เข้าใช้ล่าสุด</th>
                    <th class="p-3 text-center">การจัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-3"><?php echo $u['id']; ?></td>
                    <td class="p-3 font-bold text-blue-600"><?php echo $u['username']; ?></td>
                    <td class="p-3"><?php echo $u['fullname']; ?></td>
                    
                    <td class="p-3 text-center">
                        <form method="post" class="flex items-center justify-center gap-1">
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <select name="new_role" class="border rounded px-2 py-1 text-xs" onchange="this.form.submit()">
                                <option value="viewer" <?php if($u['role']=='viewer') echo 'selected'; ?>>Viewer</option>
                                <option value="accountant" <?php if($u['role']=='accountant') echo 'selected'; ?>>Accountant</option>
                                <option value="admin" <?php if($u['role']=='admin') echo 'selected'; ?>>Admin</option>
                            </select>
                        </form>
                    </td>

                    <td class="p-3 text-center">
                        <?php if($u['status'] == 'pending'): ?>
                            <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded font-bold text-xs"><i class="fa-solid fa-clock"></i> รออนุมัติ</span>
                        <?php elseif($u['status'] == 'approved'): ?>
                            <span class="bg-green-100 text-green-700 px-2 py-1 rounded font-bold text-xs"><i class="fa-solid fa-check"></i> อนุมัติแล้ว</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 px-2 py-1 rounded font-bold text-xs"><i class="fa-solid fa-ban"></i> ระงับ</span>
                        <?php endif; ?>
                    </td>

                    <td class="p-3 text-gray-500 text-xs"><?php echo $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '-'; ?></td>
                    
                    <td class="p-3 text-center">
                        <form method="post" class="inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <?php if($u['status'] != 'approved'): ?>
                                <button type="submit" name="action" value="approve" class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600 font-bold">อนุมัติ</button>
                            <?php endif; ?>
                            
                            <?php if($u['status'] != 'rejected'): ?>
                                <button type="submit" name="action" value="reject" class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 font-bold" onclick="return confirm('ระงับผู้ใช้นี้?')">ระงับ</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>