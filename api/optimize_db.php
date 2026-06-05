<?php
/**
 * Database Optimization Script
 * Creates missing indexes for performance.
 */
require_once __DIR__ . '/../config/db.php';

// Avoid outputting HTML if this is included inside another file that doesn't want it,
// but we'll print simple logs for debugging.
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    echo "<h2>Database Performance Optimization</h2>";
} else {
    echo "<br><strong>Running Performance Optimizations...</strong><br>";
}

function addIndexIfNotExists($pdo, $table, $indexName, $columns) {
    try {
        // Check if table exists first to avoid fatal errors if running on fresh DB
        $tableCheck = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->rowCount() == 0) {
            echo "<p style='color: orange'>⚠️ Table <strong>$table</strong> does not exist yet. Skipping index.</p>";
            return;
        }

        $stmt = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("CREATE INDEX `$indexName` ON `$table` ($columns)");
            echo "<p style='color: green'>✅ Index <strong>$indexName</strong> added to <strong>$table</strong>.</p>";
        } else {
            echo "<p style='color: orange'>⚠️ Index <strong>$indexName</strong> already exists in <strong>$table</strong>.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red'>❌ Error adding index $indexName: " . $e->getMessage() . "</p>";
    }
}

try {
    // 1. mcq_attempts (user_id, chapter_id) for record_mcq_attempt.php
    addIndexIfNotExists($pdo, 'mcq_attempts', 'idx_user_chapter', 'user_id, chapter_id');
    
    // 2. mcq_scores (user_id) for get_mcq_leaderboard.php joins
    addIndexIfNotExists($pdo, 'mcq_scores', 'idx_user_id', 'user_id');
    
    // 3. users (class_id, user_type) for get_mcq_leaderboard.php WHERE clauses
    addIndexIfNotExists($pdo, 'users', 'idx_class_user_type', 'class_id, user_type');
    
    // 4. class_updates (class_id, update_type) for Worksheet filtering
    addIndexIfNotExists($pdo, 'class_updates', 'idx_class_update_type', 'class_id, update_type');

    // --- PHASE 2: Gamification & Chat Optimization ---
    
    // 5. messages (Chat System optimizations for sorting)
    addIndexIfNotExists($pdo, 'messages', 'idx_class_receiver_created', 'class_code, receiver_id, created_at');
    addIndexIfNotExists($pdo, 'messages', 'idx_sender_receiver_created', 'sender_id, receiver_id, created_at');
    
    // 6. student_progress (Gamification progress)
    addIndexIfNotExists($pdo, 'student_progress', 'idx_student_user_chapter', 'user_id, chapter_id');
    
    // 7. Vocab System
    addIndexIfNotExists($pdo, 'user_vocab_progress', 'idx_vocab_user_word', 'user_id, word_id');
    addIndexIfNotExists($pdo, 'vocab_words', 'idx_vocab_set_number', 'set_number');
    addIndexIfNotExists($pdo, 'user_vocab_stats', 'idx_vocab_stats_user', 'user_id');

    // 8. Teacher Classes (class_code) mapping optimization
    addIndexIfNotExists($pdo, 'teacher_classes', 'idx_tc_class_code', 'class_code');

    // 9. Videos and Notes chapter_id index optimization
    addIndexIfNotExists($pdo, 'videos', 'idx_videos_chapter_id', 'chapter_id');
    addIndexIfNotExists($pdo, 'notes', 'idx_notes_chapter_id', 'chapter_id');

    if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
        echo "<p><strong>Optimization Complete!</strong></p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>❌ Critical Error: " . $e->getMessage() . "</p>";
}
?>
