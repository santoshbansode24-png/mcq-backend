<?php
require '../config/db.php';
$tables = ['users', 'mental_math_progress', 'vocab_progress', 'vocab_bookmarks', 'mcq_progress', 'chapter_completion', 'classrooms', 'student_class_mapping'];

foreach($tables as $t) {
    echo "\n--- Table: $t ---\n";
    try {
        $cols = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        foreach($cols as $c) {
            echo "- " . $c['Field'] . " (" . $c['Type'] . ") KEY:" . $c['Key'] . "\n";
        }
        
        // Also check Foreign Keys
        $fk_query = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND REFERENCED_TABLE_NAME IS NOT NULL";
        $fks = $pdo->query($fk_query)->fetchAll(PDO::FETCH_ASSOC);
        if ($fks) {
            echo "Foreign Keys:\n";
            foreach($fks as $fk) {
                echo "  -> " . $fk['COLUMN_NAME'] . " references " . $fk['REFERENCED_TABLE_NAME'] . "(" . $fk['REFERENCED_COLUMN_NAME'] . ")\n";
            }
        }
    } catch(Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
