<?php
require_once 'backend/config/db.php';
try {
    $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN task_type VARCHAR(50) NOT NULL");
    echo "Successfully converted task_type to VARCHAR(50)";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
