<?php
/**
 * Direct Production DB Diagnostic Script
 */

// Production credentials from check_user.php
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
    
    echo "✅ Successfully connected to Production Database!\n\n";
    
    // 1. Search for users with mobile '7755952198' or email 'santoshbansode24@gmail.com'
    echo "--- Querying Accounts ---\n";
    $stmt = $pdo->query("SELECT user_id, name, email, mobile, password, user_type, subscription_status, subscription_expiry, last_login FROM users WHERE mobile = '7755952198' OR email = 'santoshbansode24@gmail.com' OR email = 'sbansode2021@gmail.com'");
    $users = $stmt->fetchAll();
    
    foreach ($users as $u) {
        echo "User ID: {$u['user_id']}\n";
        echo "Name: {$u['name']}\n";
        echo "Email: {$u['email']}\n";
        echo "Mobile: {$u['mobile']}\n";
        echo "User Type: {$u['user_type']}\n";
        echo "Hash: {$u['password']}\n";
        echo "Last Login: {$u['last_login']}\n";
        echo "---------------------------\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
