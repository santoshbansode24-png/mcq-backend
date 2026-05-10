<?php
require_once 'config/db.php';

try {
    echo "Checking indexes...\n";

    // Table-Column Index Map
    $indexes = [
        'videos' => 'chapter_id', 
        'notes' => 'chapter_id', 
        'mcqs' => 'chapter_id',
        'subjects' => 'class_id',
        'chapters' => 'subject_id',
        'mcq_attempts' => 'user_id',
        'content_progress' => 'user_id'
    ];

    foreach ($indexes as $table => $column) {
        $stmt = $pdo->prepare("SHOW INDEX FROM $table WHERE Column_name = ?");
        $stmt->execute([$column]);

        if ($stmt->rowCount() == 0) {
            echo "Adding index to $table($column)...\n";
            $pdo->exec("ALTER TABLE $table ADD INDEX idx_{$column} ($column)");
            echo "Index added to $table.\n";
        }
    }

    // Composite Indexes for Performance
    $composite_indexes = [
        ['table' => 'study_tasks', 'name' => 'idx_user_date', 'cols' => 'user_id, task_date'],
        ['table' => 'mcq_attempts', 'name' => 'idx_user_chapter', 'cols' => 'user_id, chapter_id'],
        ['table' => 'content_progress', 'name' => 'idx_user_chapter_type', 'cols' => 'user_id, chapter_id, content_type']
    ];

    foreach ($composite_indexes as $ci) {
        $stmt = $pdo->prepare("SHOW INDEX FROM {$ci['table']} WHERE Key_name = ?");
        $stmt->execute([$ci['name']]);
        if ($stmt->rowCount() == 0) {
            echo "Adding composite index {$ci['name']} to {$ci['table']}...\n";
            $pdo->exec("ALTER TABLE {$ci['table']} ADD INDEX {$ci['name']} ({$ci['cols']})");
        }
    }

    echo "Optimization complete.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
