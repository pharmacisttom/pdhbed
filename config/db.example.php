<?php
// Copy this file to config/db.php and set real credentials on the server.

$host = getenv('HIS_DB_HOST') ?: '127.0.0.1';
$db = getenv('HIS_DB_NAME') ?: 'ipd';
$user = getenv('HIS_DB_USER') ?: '';
$pass = getenv('HIS_DB_PASS') ?: '';
$charset = getenv('HIS_DB_CHARSET') ?: 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec('SET NAMES tis620');
} catch (PDOException $e) {
    die('Database Connection Failed : ' . $e->getMessage());
}
?>
