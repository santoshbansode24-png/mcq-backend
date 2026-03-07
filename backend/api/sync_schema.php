<?php
/**
 * Master Database Sync for Railway
 * Automatically creates all missing tables for progress tracking, sync, and more.
 * 
 * Instructions: Visit https://api.veeruapp.in/backend/api/sync_schema.php in your browser.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== VEERU DATABASE SCHEMA SYNC ===\n\n";

$sql_chunks = [
    "content_progress" => "CREATE TABLE IF NOT EXISTS content_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chapter_id INT NOT NULL,
        content_type ENUM('mcq', 'flashcard') NOT NULL,
        set_index INT NOT NULL DEFAULT 0,
        status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
        score INT DEFAULT 0,
        total INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_content_set (user_id, chapter_id, content_type, set_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "mcq_attempts" => "CREATE TABLE IF NOT EXISTS mcq_attempts (
        attempt_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mcq_id INT NOT NULL,
        chapter_id INT NOT NULL,
        selected_answer VARCHAR(1),
        correct_answer VARCHAR(1),
        is_correct BOOLEAN,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_chapter (user_id, chapter_id),
        UNIQUE KEY unique_attempt (user_id, mcq_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "student_progress" => "CREATE TABLE IF NOT EXISTS student_progress (
        progress_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chapter_id INT NOT NULL,
        completed_mcqs INT DEFAULT 0,
        total_mcqs INT DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0.00,
        last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_chapter (user_id, chapter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "app_content_updates" => "CREATE TABLE IF NOT EXISTS app_content_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        version_timestamp BIGINT NOT NULL,
        update_type VARCHAR(20) NOT NULL,
        item_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_class_sync (class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "flashcard_progress" => "CREATE TABLE IF NOT EXISTS flashcard_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chapter_id INT NOT NULL,
        set_index INT NOT NULL,
        completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attempt (user_id, chapter_id, set_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($sql_chunks as $table_name => $sql) {
    echo "Processing $table_name... ";
    try {
        $pdo->exec($sql);
        echo "✅ OK\n";
    } catch (PDOException $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\nSchema sync completed!";
?>
