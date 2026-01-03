<?php
// Test DB Connection
header('Content-Type: application/json');

$host = '127.0.0.1';
$db   = 'veeru_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo json_encode(['status' => 'success', 'message' => 'Connected to 127.0.0.1 successfully']);
} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'code' => $e->getCode()]);
}
?>
