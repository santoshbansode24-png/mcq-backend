<?php
$host = 'yamanote.proxy.rlwy.net';
$port = '24540';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$dbname = 'railway';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    
    echo "Connected successfully to live database.\n";
    
    // Describe class_updates table
    echo "\n--- Describe class_updates ---\n";
    $stmt = $pdo->query("DESCRIBE class_updates");
    print_r($stmt->fetchAll());

    echo "\n--- Recent Class Updates for class_id = 23 ---\n";
    $stmt = $pdo->query("SELECT * FROM class_updates WHERE class_id = 23 ORDER BY id DESC LIMIT 5");
    $updates = $stmt->fetchAll();
    print_r($updates);

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>
