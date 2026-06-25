<?php
$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$db = 'railway';
$port = 24540;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected to DB\n";
    $stmt = $pdo->query("SHOW INDEX FROM users");
    $indexes = $stmt->fetchAll();
    foreach ($indexes as $idx) {
        echo "Table: {$idx['Table']} | Non_unique: {$idx['Non_unique']} | Key_name: {$idx['Key_name']} | Seq_in_index: {$idx['Seq_in_index']} | Column_name: {$idx['Column_name']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
