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
    
    // Check count of each update_type
    $stmt = $pdo->query("SELECT update_type, COUNT(*) as cnt FROM class_updates GROUP BY update_type");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Check all exams
    $stmt = $pdo->query("SELECT * FROM class_updates WHERE update_type = 'exam'");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
