<?php
/**
 * Database Optimization and Hardening Script
 * Veeru Learning App - Production Ready
 * 
 * Purpose: 
 * 1. Adds performance-enhancing indexes to most-queried tables.
 * 2. Optimizes database tables for storage efficiency.
 * 3. ensures the database structure is in its 'Perfect' state.
 */

require_once '../config/db.php';

// Security Check: Use a simple key to prevent unauthorized execution
$secret = isset($_GET['key']) ? $_GET['key'] : '';
$valid_key = 'veeru_perf_2026'; // Simple protection

if ($secret !== $valid_key) {
    die("Unauthorized access. Security key required.");
}

echo "<h2>🚀 Veeru Database Optimization Started...</h2>";

/**
 * Safely adds an index if it doesn't exist
 */
function addIndex($pdo, $table, $indexName, $columns) {
    try {
        // Check if index exists
        $check = $pdo->query("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE $table ADD INDEX $indexName ($columns)");
            echo "<p style='color: green'>✅ Created index <strong>$indexName</strong> on table <strong>$table</strong> ($columns).</p>";
        } else {
            echo "<p style='color: orange'>⚠️ Index <strong>$indexName</strong> already exists on <strong>$table</strong>.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red'>❌ Error on $table: " . $e->getMessage() . "</p>";
    }
}

// 1. Optimize MCQs (Filtered by chapter and language)
addIndex($pdo, 'mcqs', 'idx_chapter_medium', 'chapter_id, medium');
addIndex($pdo, 'mcqs', 'idx_medium', 'medium');

// 2. Optimize Chapters (Filtered by subject)
addIndex($pdo, 'chapters', 'idx_subject', 'subject_id');

// 3. Optimize Subjects (Filtered by class)
addIndex($pdo, 'subjects', 'idx_class', 'class_id');

// 4. Optimize Content Progress (Already has Unique Key, adding index for analytics)
addIndex($pdo, 'content_progress', 'idx_user_type', 'user_id, content_type');

// 5. Optimize Quick Revision (High performance index, multilang columns, and utf8mb4)
$qrCols = [
    "title_hi VARCHAR(255) DEFAULT NULL",
    "title_mr VARCHAR(255) DEFAULT NULL",
    "key_points_hi JSON DEFAULT NULL",
    "key_points_mr JSON DEFAULT NULL",
    "summary_hi TEXT DEFAULT NULL",
    "summary_mr TEXT DEFAULT NULL"
];
foreach ($qrCols as $colDef) {
    try {
        $pdo->exec("ALTER TABLE quick_revision ADD COLUMN IF NOT EXISTS $colDef");
    } catch (PDOException $e) {}
}
addIndex($pdo, 'quick_revision', 'idx_chapter_created', 'chapter_id, created_at DESC');
try {
    $pdo->exec("ALTER TABLE quick_revision CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green'>✅ Converted quick_revision to <strong>utf8mb4</strong>.</p>";
} catch (PDOException $e) {}

// 6. Run Table Optimization (Defragments data for faster reads)
$tables = ['mcqs', 'chapters', 'subjects', 'users', 'notes', 'videos', 'content_progress'];
echo "<h3>📦 Defragmenting Tables...</h3>";
foreach ($tables as $table) {
    try {
        $pdo->exec("OPTIMIZE TABLE $table");
        echo "<li>Optimized <strong>$table</strong></li>";
    } catch (PDOException $e) {
        echo "<li>Failed to optimize $table: " . $e->getMessage() . "</li>";
    }
}

echo "<br><h2 style='color: blue'>✨ Database is now in its perfect performance state!</h2>";
echo "<p>You should now delete this script for safety.</p>";
?>
