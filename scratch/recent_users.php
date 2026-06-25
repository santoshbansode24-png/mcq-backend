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
    $stmt = $pdo->query("SELECT user_id, name, email, mobile, user_type, created_at FROM users ORDER BY user_id DESC LIMIT 20");
    $users = $stmt->fetchAll();
    echo "Last 20 registered users:\n";
    foreach ($users as $u) {
        echo "ID: {$u['user_id']} | Name: {$u['name']} | Email: {$u['email']} | Mobile: {$u['mobile']} | Type: {$u['user_type']} | Created: {$u['created_at']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
