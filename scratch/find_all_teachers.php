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
    $stmt = $pdo->query("SELECT user_id, name, email, mobile, user_type FROM users WHERE LOWER(user_type) = 'teacher'");
    $teachers = $stmt->fetchAll();
    echo "Found " . count($teachers) . " teachers:\n";
    foreach ($teachers as $t) {
        echo "ID: {$t['user_id']} | Name: {$t['name']} | Email: {$t['email']} | Mobile: {$t['mobile']} | Type: {$t['user_type']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
