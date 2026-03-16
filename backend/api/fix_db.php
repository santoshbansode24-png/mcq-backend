<?php
require_once '../config/db.php';
try {
    $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN task_type VARCHAR(50) NOT NULL");
    $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN title VARCHAR(255) NOT NULL");
    $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN subject VARCHAR(100) NOT NULL");
    echo "Database updated successfully! The 'task_type' issue is now permanently solved. Please try generating your roadmap again.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
