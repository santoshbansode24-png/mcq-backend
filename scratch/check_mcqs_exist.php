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
    
    $ids = [1283, 1327, 1257, 1277, 1319, 1318, 1248, 1252, 1258, 1328];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $pdo->prepare("SELECT mcq_id, question FROM mcqs WHERE mcq_id IN ($placeholders)");
    $stmt->execute($ids);
    $results = $stmt->fetchAll();
    
    echo "Found " . count($results) . " matching MCQs out of " . count($ids) . "\n";
    print_r($results);

} catch (PDOException $e) {
    echo "Query failed: " . $e->getMessage() . "\n";
}
?>
