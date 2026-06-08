<?php
$railway_host = 'yamanote.proxy.rlwy.net';
$railway_port = 24540;
$railway_user = 'root';
$railway_pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$railway_db = 'railway';

try {
    $dsn = "mysql:host=$railway_host;port=$railway_port;dbname=$railway_db;charset=utf8mb4";
    $pdo = new PDO($dsn, $railway_user, $railway_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->query("SELECT update_id, title, update_type, payload, created_at FROM class_updates ORDER BY update_id DESC LIMIT 5");
    $rows = $stmt->fetchAll();
    
    echo "=== RECENT CLASS UPDATES ===\n";
    foreach ($rows as $row) {
        echo "ID: " . $row['update_id'] . "\n";
        echo "Title: " . $row['title'] . "\n";
        echo "Type: " . $row['update_type'] . "\n";
        echo "Payload: " . $row['payload'] . "\n";
        echo "Created At: " . $row['created_at'] . "\n";
        echo "---------------------------------\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
