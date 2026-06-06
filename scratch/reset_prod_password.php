<?php
/**
 * Script to reset production user password for testing
 */

$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$db = 'railway';
$port = 24540;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Connected to Production Database.\n";
    
    // Hash password 'veeru123'
    $new_password = 'veeru123';
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update sbansode2021@gmail.com (User 8)
    $stmt1 = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = 8");
    $stmt1->execute([$hash]);
    echo "Successfully updated password for User 8 (sbansode2021@gmail.com) to 'veeru123'.\n";
    
    // Update santoshbansode24@gmail.com (User 29)
    $stmt2 = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = 29");
    $stmt2->execute([$hash]);
    echo "Successfully updated password for User 29 (santoshbansode24@gmail.com) to 'veeru123'.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
