<?php
require_once 'backend/config/db.php';
try {
    // Explicitly set the enum again to be sure
    $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN task_type ENUM('revision','quiz','video','custom','practice','notes','flashcard') NOT NULL");
    echo "Schema updated successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
