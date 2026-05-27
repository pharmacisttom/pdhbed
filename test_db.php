<?php
// ไฟล์: pdhbed/api/test_db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Start Connection Test</h1>";

require '../config/db.php';
echo "<p>✅ Database Connected successfully.</p>";

$pdo->exec("SET NAMES tis620");
echo "<p>✅ SET NAMES tis620 executed.</p>";

// ลอง Query ง่ายๆ
$sql = "SELECT * FROM ipd.ipd LIMIT 1";
$stmt = $pdo->query($sql);

if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>✅ Query OK. AN sample: " . $row['an'] . "</p>";
} else {
    echo "<p>❌ Query Failed: ";
    print_r($pdo->errorInfo());
    echo "</p>";
}
?>