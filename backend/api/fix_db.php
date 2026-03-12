<?php
require_once '../config/db.php';
try {
    $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN task_type VARCHAR(50)");
    $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN title VARCHAR(255)");
    $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN subject VARCHAR(100)");
    echo "Database updated successfully! Please try generating the roadmap again.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
