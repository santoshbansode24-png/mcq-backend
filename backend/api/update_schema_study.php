<?php
/**
 * Update Study Tasks Schema - Nuclear Option
 * Forces task_type to VARCHAR(50) by dropping and recreating.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // 1. Add chapter_ids column if missing
    try {
        $pdo->exec("ALTER TABLE study_tasks ADD COLUMN chapter_ids TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignore if error is duplicate column
    }

    // 2. Proactively drop task_type to clear all ENUM strict-mode legacy
    try {
        $pdo->exec("ALTER TABLE study_tasks DROP COLUMN task_type");
    } catch (PDOException $e) {
         // Ignore if already dropped
    }

    // 3. Re-add as flexible VARCHAR
    $pdo->exec("ALTER TABLE study_tasks ADD COLUMN task_type VARCHAR(50) NOT NULL DEFAULT 'custom'");

    echo json_encode([
        'status' => 'success',
        'message' => 'Schema NUKED and REBUILT successfully. task_type is now VARCHAR.',
        'version' => '3.5-nuclear-final'
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Nuclear Update Failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
