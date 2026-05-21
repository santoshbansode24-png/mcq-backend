<?php
require_once 'c:/xampp/htdocs/veeru/backend/config/db.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "Tables missing common foreign key indexes:\n";

$fk_patterns = ['user_id', 'chapter_id', 'class_id', 'teacher_id', 'subject_id', 'student_id'];

foreach ($tables as $table) {
    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll();
    
    $indexed_cols = [];
    foreach ($indexes as $idx) {
        $indexed_cols[] = $idx['Column_name'];
    }
    
    foreach ($columns as $col) {
        if (in_array($col, $fk_patterns)) {
            if (!in_array($col, $indexed_cols)) {
                echo "- $table is missing index on $col\n";
            }
        }
    }
}
echo "Done.\n";
?>
