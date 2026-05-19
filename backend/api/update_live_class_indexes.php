<?php
/**
 * Database Migration Script: Optimize Live Class Indexes
 * 
 * Run this script to add missing indexes for fast concurrent polling.
 */
require_once '../config/db.php';

header('Content-Type: text/plain');
echo "Database Optimizer: Live Class Indexes\n";
echo "======================================\n\n";

function addIndexSafely($pdo, $table, $indexName, $columns) {
    try {
        // Check if index already exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND INDEX_NAME = ?
        ");
        $stmt->execute([$table, $indexName]);
        $exists = intval($stmt->fetchColumn()) > 0;

        if ($exists) {
            echo "ℹ️ Index '$indexName' already exists on table '$table'. Skipping.\n";
            return;
        }

        // Add the index
        $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` ($columns)";
        $pdo->exec($sql);
        echo "✅ Successfully added index '$indexName' to table '$table'.\n";

    } catch (PDOException $e) {
        echo "❌ Failed to add index '$indexName' to table '$table': " . $e->getMessage() . "\n";
    }
}

// 1. Optimize attendance queries: WHERE class_update_id = ? AND joined_at >= ...
addIndexSafely($pdo, 'live_class_attendance', 'idx_class_update_joined', 'class_update_id, joined_at');

// 2. Optimize chat queries: WHERE class_update_id = ? AND id > ?
addIndexSafely($pdo, 'live_class_chat', 'idx_class_id_id', 'class_update_id, id');

// 3. Optimize reaction queries: WHERE class_update_id = ? AND id > ?
addIndexSafely($pdo, 'live_class_reactions', 'idx_class_id_id', 'class_update_id, id');

echo "\nOptimization complete.\n";
?>
