<?php
$host = 'yamanote.proxy.rlwy.net';
$port = 24540;
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$db   = 'railway';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "--- latest 5 class_updates for class_id = 23 ---\n";
    $stmt = $pdo->query("SELECT id, class_id, update_type, title, message, payload, created_at FROM class_updates WHERE class_id = 23 ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo "ID: " . $row['id'] . "\n";
        echo "Type: " . $row['update_type'] . "\n";
        echo "Title: " . $row['title'] . "\n";
        echo "Message: " . $row['message'] . "\n";
        echo "Payload: " . $row['payload'] . "\n";
        echo "Created At: " . $row['created_at'] . "\n";
        echo "---------------------------------\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>

