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
    
    echo "PHP Timezone: " . date_default_timezone_get() . "\n";
    echo "PHP Current Time: " . date('Y-m-d H:i:s') . " (Timestamp: " . time() . ")\n";
    
    // MySQL Timezones
    $stmt = $pdo->query("SELECT @@global.time_zone, @@session.time_zone, NOW()");
    $mysqlTime = $stmt->fetch();
    echo "MySQL Global Timezone: " . $mysqlTime['@@global.time_zone'] . "\n";
    echo "MySQL Session Timezone: " . $mysqlTime['@@session.time_zone'] . "\n";
    echo "MySQL NOW(): " . $mysqlTime['NOW()'] . "\n";

    // Test with the latest live exam
    $stmt = $pdo->query("SELECT id, created_at, duration_minutes, status FROM live_exams ORDER BY id DESC LIMIT 1");
    $exam = $stmt->fetch();
    
    if ($exam) {
        echo "\nLatest Exam Details:\n";
        echo "ID: " . $exam['id'] . "\n";
        echo "Created At: " . $exam['created_at'] . "\n";
        echo "Duration Minutes: " . $exam['duration_minutes'] . "\n";
        echo "Status: " . $exam['status'] . "\n";
        
        $createdAt = strtotime($exam['created_at']);
        $durationSeconds = $exam['duration_minutes'] * 60;
        $expiryTime = $createdAt + $durationSeconds;
        $currentTime = time();
        
        echo "\nCalculations:\n";
        echo "createdAt Timestamp: $createdAt (" . date('Y-m-d H:i:s', $createdAt) . ")\n";
        echo "expiryTime Timestamp: $expiryTime (" . date('Y-m-d H:i:s', $expiryTime) . ")\n";
        echo "currentTime Timestamp: $currentTime (" . date('Y-m-d H:i:s', $currentTime) . ")\n";
        echo "Is expired? " . ($currentTime > $expiryTime ? "YES" : "NO") . "\n";
    }

} catch (PDOException $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
?>
