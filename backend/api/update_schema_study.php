<?php
/**
 * Update Study Tasks Schema
 * Adds new task types to the ENUM
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // Add chapter_ids column if missing
    try {
        $pdo->exec("ALTER TABLE study_tasks ADD COLUMN chapter_ids TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignore if error is duplicate column
    }

    // Convert task_type to VARCHAR(50) to permanently prevent any ENUM truncation errors
    $sql = "ALTER TABLE study_tasks MODIFY COLUMN task_type VARCHAR(50) NOT NULL DEFAULT 'custom'";
    $pdo->exec($sql);

    echo json_encode([
        'status' => 'success',
        'message' => 'study_tasks table updated with new task types.'
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Schema Update Failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
