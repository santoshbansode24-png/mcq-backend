<?php
require_once 'config/db.php';

$tables = ['user_vocab_progress', 'vocab_review_history', 'user_vocab_stats', 'vocab_words'];
$results = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $results[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $results[$table] = "Error: " . $e->getMessage();
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
