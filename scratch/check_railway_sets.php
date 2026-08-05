<?php
$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$stmt = $pdo->query("SELECT set_number, COUNT(*) as count FROM vocab_words GROUP BY set_number ORDER BY set_number ASC");
$rows = $stmt->fetchAll();

echo "LIVE RAILWAY DATABASE SET NUMBERS:\n";
foreach ($rows as $r) {
    echo "Set {$r['set_number']}: {$r['count']} words\n";
}
?>
