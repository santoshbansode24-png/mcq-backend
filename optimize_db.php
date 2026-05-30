<?php
require_once 'config/db.php';

echo "<h2>Optimizing Database Indexes...</h2>\n";

function addIndex($pdo, $table, $index_name, $columns) {
    try {
        $pdo->exec("CREATE INDEX $index_name ON $table ($columns)");
        echo "✅ Added index $index_name on $table($columns)\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️ Index $index_name already exists on $table.\n";
        } else {
            echo "❌ Error adding index $index_name: " . $e->getMessage() . "\n";
        }
    }
}

// Ensure proper indexes on heavily joined tables
addIndex($pdo, 'classrooms', 'idx_classrooms_class_code', 'class_code');
addIndex($pdo, 'student_class_mapping', 'idx_scm_class_id', 'class_id');
addIndex($pdo, 'student_class_mapping', 'idx_scm_student_id', 'student_id');
addIndex($pdo, 'teacher_classes', 'idx_tc_class_id', 'class_id');
addIndex($pdo, 'class_updates', 'idx_cu_teacher_id', 'teacher_id');

echo "Optimization complete.\n";
?>
