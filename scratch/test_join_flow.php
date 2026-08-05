<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'veeru_db');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected to MySQL successfully!\n";
    
    $stmt = $pdo->query("SELECT class_code FROM teacher_classes LIMIT 1");
    $row = $stmt->fetch();
    echo "Class Code found: " . ($row['class_code'] ?? 'None') . "\n";

} catch (Exception $e) {
    echo "MySQL Note: " . $e->getMessage() . "\n";
}
?>
