<?php
// Copy this file to api/db_connect_pdo.php and set real credentials on the server.
declare(strict_types=1);

function get_connection($type = 'app', $db_name = '', $charset = 'utf8mb4') {
    if ($type === 'app') {
        $host = getenv('APP_DB_HOST') ?: '127.0.0.1';
        $db = $db_name !== '' ? $db_name : (getenv('APP_DB_NAME') ?: 'test');
        $user = getenv('APP_DB_USER') ?: '';
        $pass = getenv('APP_DB_PASS') ?: '';
        $charset = getenv('APP_DB_CHARSET') ?: 'utf8mb4';
    } elseif ($type === 'his') {
        $host = getenv('HIS_DB_HOST') ?: '127.0.0.1';
        $db = $db_name !== '' ? $db_name : (getenv('HIS_DB_NAME') ?: 'opd');
        $user = getenv('HIS_DB_USER') ?: '';
        $pass = getenv('HIS_DB_PASS') ?: '';
        $charset = getenv('HIS_DB_CHARSET') ?: 'tis620';
    } else {
        throw new Exception('Unknown database connection type: ' . $type);
    }

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        if ($charset === 'tis620') {
            $pdo->exec('SET NAMES tis620');
        }
        return $pdo;
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => 'Database Connection Failed: ' . $e->getMessage()]);
        exit;
    }
}
?>
