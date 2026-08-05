<?php
$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

echo "🚀 Connecting to Railway DB...\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Inspect processlist
    $stmt = $pdo->query("SHOW FULL PROCESSLIST");
    $processes = $stmt->fetchAll();
    echo "Active MySQL Threads: " . count($processes) . "\n";

    $myId = $pdo->query("SELECT CONNECTION_ID()")->fetchColumn();

    foreach ($processes as $proc) {
        $id = $proc['Id'];
        $command = $proc['Command'];
        $time = $proc['Time'];
        $info = $proc['Info'];
        
        if ($id != $myId && ($command === 'Sleep' || $time > 10)) {
            echo "   Killing thread #$id (Command: $command, Time: {$time}s)\n";
            try {
                $pdo->exec("KILL $id");
            } catch (Exception $e) {
                // Ignore
            }
        }
    }

    echo "✅ Cleaned hanging connections.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
