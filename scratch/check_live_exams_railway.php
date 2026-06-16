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
    
    // Check tables
    echo "\n--- Show Tables ---\n";
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo $row[0] . "\n";
    }

    echo "\n--- Live Exams ---\n";
    $stmt = $pdo->query("SELECT * FROM live_exams ORDER BY id DESC LIMIT 5");
    $exams = $stmt->fetchAll();
    print_r($exams);

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>
