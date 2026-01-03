<?php
// Simple script to check vocabulary count
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query("SELECT set_number, COUNT(*) as count FROM vocab_words GROUP BY set_number ORDER BY set_number");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "SET | COUNT\n";
    echo "----|------\n";
    foreach ($results as $row) {
        echo str_pad($row['set_number'], 3) . " | " . $row['count'] . "\n";
    }
    
    // Total
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM vocab_words");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal words: " . $total['total'] . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
