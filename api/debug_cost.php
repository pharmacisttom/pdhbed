<?php
// ไฟล์: pdhbed/api/debug_cost.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 นาที
header('Content-Type: text/html; charset=utf-8');
?>
<style>
    body { font-family: sans-serif; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #ccc; padding: 5px; font-size: 12px; }
    .ok { color: green; font-weight: bold; }
    .err { color: red; font-weight: bold; }
    .row { background: #f9f9f9; }
</style>
<h2>🕵️‍♂️ ทดสอบดึงข้อมูล + คำนวณเงิน (แก้ไขเงื่อนไขวันที่)</h2>

<?php
echo "1. กำลังเชื่อมต่อ Database... ";
try {
    require '../config/db.php';
    $pdo->exec("SET NAMES tis620");
    echo "<span class='ok'>✅ สำเร็จ</span><br>";
} catch (Exception $e) {
    die("<span class='err'>❌ ล้มเหลว: " . $e->getMessage() . "</span>");
}

echo "2. กำลังดึงรายชื่อผู้ป่วย (Limit 20)... ";
try {
    // --- แก้ไขจุดผิดตรงนี้: เพิ่ม OR i.datedsc = '0000-00-00' ---
    $sql = "SELECT i.an, i.hn, o.fullname 
            FROM ipd.ipd i 
            LEFT JOIN opd.opd o ON i.hn = o.hn 
            WHERE (i.datedsc IS NULL OR i.datedsc = '' OR i.datedsc = '0000-00-00') 
            ORDER BY i.bed ASC LIMIT 20";
            
    $stmt = $pdo->query($sql);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<span class='ok'>✅ พบ " . count($patients) . " คน</span><br>";
} catch (Exception $e) {
    die("<span class='err'>❌ Query พัง: " . $e->getMessage() . "</span>");
}

if(empty($patients)) die("จบการทำงาน (ไม่พบผู้ป่วย)");

echo "3. กำลังคำนวณเงิน...<br><br>";
echo "<table>";
echo "<tr style='background:#333; color:#fff'><th>HN</th><th>AN</th><th>ชื่อ</th><th>ค่ายา</th><th>ค่า Lab</th><th>ค่า X-ray</th><th>อื่นๆ</th><th>รวม</th></tr>";

function getPrice($pdo, $table, $an) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(price) FROM $table WHERE an = ?");
        $stmt->execute([$an]);
        return (float)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}

foreach ($patients as $pt) {
    $an = $pt['an'];
    $name = @iconv('TIS-620', 'UTF-8//IGNORE', $pt['fullname']);
    
    // ดึงเงินทีละส่วน
    $d = getPrice($pdo, 'ipd.drug_order_ipd', $an);
    $l = getPrice($pdo, 'ipd.lab_order_ipd', $an);
    $x = getPrice($pdo, 'ipd.xray_order_ipd', $an);
    $o = getPrice($pdo, 'ipd.other_order_ipd', $an);
    $total = $d + $l + $x + $o;

    echo "<tr class='row'>";
    echo "<td>{$pt['hn']}</td>";
    echo "<td>{$an}</td>";
    echo "<td>{$name}</td>";
    echo "<td>" . number_format($d, 2) . "</td>";
    echo "<td>" . number_format($l, 2) . "</td>";
    echo "<td>" . number_format($x, 2) . "</td>";
    echo "<td>" . number_format($o, 2) . "</td>";
    echo "<td style='font-weight:bold; color:blue'>" . number_format($total, 2) . "</td>";
    echo "</tr>";
    
    flush(); 
    ob_flush();
}
echo "</table>";
?>