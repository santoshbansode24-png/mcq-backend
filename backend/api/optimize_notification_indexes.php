<?php
/**
 * Database Migration Script: Optimize Notifications and Class Updates Indexes
 * 
 * Run this script to add missing composite indexes for faster notification feeds.
 */
require_once '../config/db.php';
require_once 'cors_middleware.php';

header('Content-Type: text/plain');
echo "Database Optimizer: Notifications & Class Updates Indexes\n";
echo "=========================================================\n\n";

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

// 1. Optimize class_updates: WHERE class_id = ? ORDER BY created_at DESC
addIndexSafely($pdo, 'class_updates', 'idx_class_created', 'class_id, created_at DESC');

// 2. Optimize notifications: WHERE class_id = ? ORDER BY created_at DESC
addIndexSafely($pdo, 'notifications', 'idx_class_created', 'class_id, created_at DESC');

// 3. Optimize class_exam_results: WHERE user_id = ?
addIndexSafely($pdo, 'class_exam_results', 'idx_user_id', 'user_id');

echo "\nOptimization complete.\n";
?>
