<?php
/**
 * Quick Revision Schema & Index Optimizer
 * Veeru
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($pdo)) {
        throw new Exception("Database connection not found.");
    }

    $applied = [];

    // 1. Ensure table columns exist
    $columns = [
        "title_hi VARCHAR(255) DEFAULT NULL",
        "title_mr VARCHAR(255) DEFAULT NULL",
        "key_points_hi JSON DEFAULT NULL",
        "key_points_mr JSON DEFAULT NULL",
        "summary_hi TEXT DEFAULT NULL",
        "summary_mr TEXT DEFAULT NULL"
    ];

    foreach ($columns as $colDef) {
        $parts = explode(' ', $colDef);
        $colName = $parts[0];
        try {
            $pdo->exec("ALTER TABLE quick_revision ADD COLUMN IF NOT EXISTS $colDef");
            $applied[] = "Column: $colName";
        } catch (PDOException $e) {
            // Ignore if exists
        }
    }

    // 2. Ensure High-Performance Composite Index
    try {
        $checkIndex = $pdo->query("SHOW INDEX FROM quick_revision WHERE Key_name = 'idx_chapter_created'");
        if ($checkIndex->rowCount() === 0) {
            $pdo->exec("ALTER TABLE quick_revision ADD INDEX idx_chapter_created (chapter_id, created_at DESC)");
            $applied[] = "Index: idx_chapter_created";
        }
    } catch (PDOException $e) {
        // Fallback for older MySQL without DESC index
        try {
            $pdo->exec("ALTER TABLE quick_revision ADD INDEX idx_chapter_created (chapter_id, created_at)");
            $applied[] = "Index: idx_chapter_created (ASC fallback)";
        } catch (PDOException $e2) {}
    }

    // 3. Ensure Table Charset is utf8mb4
    try {
        $pdo->exec("ALTER TABLE quick_revision CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $applied[] = "Charset: utf8mb4";
    } catch (PDOException $e) {}

    sendResponse('success', 'Quick Revision schema & index optimized successfully', $applied);

} catch (Exception $e) {
    sendResponse('error', 'Optimization failed: ' . $e->getMessage());
}
