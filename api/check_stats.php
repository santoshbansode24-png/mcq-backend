<?php
require_once __DIR__ . '/../config/db.php';

$userId = 4; // Based on previous logs

try {
    echo "Checking stats for User ID: $userId\n";
    
    $stmt = $pdo->prepare("SELECT * FROM user_vocab_stats WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        echo "Found stats:\n";
        print_r($stats);
    } else {
        echo "NO STATS FOUND for user $userId\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
