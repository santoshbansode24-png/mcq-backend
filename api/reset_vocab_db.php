<?php
require_once __DIR__ . '/../config/db.php';

try {
    // Disable foreign key checks to allow truncation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Truncate tables
    $pdo->exec("TRUNCATE TABLE vocab_words");
    $pdo->exec("TRUNCATE TABLE user_vocab_progress");
    $pdo->exec("TRUNCATE TABLE user_vocab_stats");
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Check actual count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vocab_words");
    $count = $stmt->fetchColumn();
    
    $stmt2 = $pdo->query("SELECT * FROM vocab_words LIMIT 5");
    $samples = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'count' => $count, 'samples' => $samples]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
