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
    
    // Update sbansode2021@gmail.com (User ID 8)
    $stmt1 = $pdo->prepare("UPDATE users SET user_type = 'teacher', subscription_status = 'active' WHERE user_id = 8");
    $stmt1->execute();
    echo "Updated User ID 8 (sbansode2021@gmail.com) to teacher.\n";
    
    // Update santoshbansode24@gmail.com (User ID 29)
    $stmt2 = $pdo->prepare("UPDATE users SET user_type = 'teacher', subscription_status = 'active' WHERE user_id = 29");
    $stmt2->execute();
    echo "Updated User ID 29 (santoshbansode24@gmail.com) to teacher.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
