<?php
require_once 'config/db.php';

try {
    echo "<h2>Flashcards by Chapter:</h2>";
    $stmt = $pdo->query("SELECT chapter_id, COUNT(*) as count FROM flashcards GROUP BY chapter_id");
    echo "<pre>" . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<h2>First 5 Flashcards:</h2>";
    $stmt = $pdo->query("SELECT * FROM flashcards LIMIT 5");
    echo "<pre>" . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "</pre>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
