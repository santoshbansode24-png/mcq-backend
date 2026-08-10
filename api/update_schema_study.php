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
    
    // 4. Update pdf_study_jobs schema for Veeru Lens (Fixes missing columns error)
    $colsToAdd = [
        'file_hash'    => "ALTER TABLE pdf_study_jobs ADD COLUMN file_hash VARCHAR(64) DEFAULT NULL AFTER file_name",
        'file_size'    => "ALTER TABLE pdf_study_jobs ADD COLUMN file_size BIGINT DEFAULT 0 AFTER file_hash",
        'current_step' => "ALTER TABLE pdf_study_jobs ADD COLUMN current_step VARCHAR(255) DEFAULT 'Queued for AI extraction' AFTER status"
    ];

    $log = [];
    foreach ($colsToAdd as $colName => $alterSql) {
        try {
            $pdo->exec($alterSql);
            $log[] = "Added $colName";
        } catch (PDOException $ex) {
            $log[] = "$colName status: " . $ex->getMessage();
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Veeru Lens columns check completed.',
        'log' => $log,
        'version' => '3.6-force-columns'
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed updating schema: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
