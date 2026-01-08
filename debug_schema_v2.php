<?php
require_once 'config/db.php';

$tables = ['mcqs', 'notes', 'videos', 'flashcards', 'quick_revision'];

foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($columns as $col) {
            echo $col['Field'] . " (" . $col['Type'] . ")\n";
        }
        echo "</pre>";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
    echo "<hr>";
}
?>
