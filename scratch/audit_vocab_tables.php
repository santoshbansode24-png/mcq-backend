<?php
/**
 * Detailed Vocab Tables Audit for Railway Production
 */

$host = 'yamanote.proxy.rlwy.net';
$port = 24540;
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$dbname = 'railway';

echo "--- CONNECTING TO RAILWAY DATABASE ---\n";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    echo "✅ Connection Successful!\n\n";

    $vocab_tables = [
        'vocab_words',
        'vocab_categories',
        'vocab_bookmarks',
        'vocab_review_history',
        'user_vocab_progress',
        'user_vocab_stats',
        'vocab_progress'
    ];

    foreach ($vocab_tables as $table) {
        echo "\nChecking Table: [$table]\n";
        try {
            $desc = $pdo->query("DESCRIBE `$table`")->fetchAll();
            foreach ($desc as $col) {
                echo "  - {$col['Field']} ({$col['Type']}) - Null: {$col['Null']}, Key: {$col['Key']}, Default: " . json_encode($col['Default']) . "\n";
            }
            // Get row count
            $cnt = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "  Row Count: $cnt\n";
        } catch (Exception $ex) {
            echo "  ❌ Failed: " . $ex->getMessage() . "\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}
?>
