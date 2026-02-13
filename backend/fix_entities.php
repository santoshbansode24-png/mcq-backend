<?php
require_once 'config/db.php';

header('Content-Type: text/plain');

$tables = [
    'mcqs' => ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'explanation'],
    'flashcards' => ['question_front', 'answer_back'],
    'notes' => ['title', 'description'],
    'quick_revision' => ['title', 'summary', 'point_1', 'point_2', 'point_3', 'point_4', 'point_5']
];

echo "Starting entity fix...\n";

foreach ($tables as $table => $columns) {
    echo "Processing table: $table\n";
    foreach ($columns as $col) {
        // SQL to replace &amp; with & and &#039; with '
        $sql = "UPDATE $table SET $col = REPLACE(REPLACE(REPLACE($col, '&amp;', '&'), '&#039;', \"'\"), '&quot;', '\"')";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            echo "  - Updated column: $col ($stmt->rowCount() rows affected)\n";
        } catch (PDOException $e) {
            echo "  - Error updating $col: " . $e->getMessage() . "\n";
        }
    }
}

echo "Done!\n";
?>
