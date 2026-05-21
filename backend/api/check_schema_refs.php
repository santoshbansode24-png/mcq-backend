<?php
require '../config/db.php';
$tables = ['subjects', 'chapters', 'mcqs', 'vocab_words'];
foreach($tables as $t) {
    echo "\n--- Table: $t ---\n";
    try {
        $cols = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        foreach($cols as $c) {
            echo "- " . $c['Field'] . " (" . $c['Type'] . ") KEY:" . $c['Key'] . "\n";
        }
    } catch(Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
