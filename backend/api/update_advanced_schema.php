<?php
/**
 * Advanced Schema Sync for Veeru App
 * Applies the comprehensive student activity and progress database schema.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== VEERU ADVANCED SCHEMA SYNC ===\n\n";

$sql_chunks = [
    // 1. Mental Math Tracking
    "mental_math_progress" => "CREATE TABLE IF NOT EXISTS `mental_math_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `session_date` DATE NOT NULL,
        `difficulty_level` ENUM('easy', 'medium', 'hard') NOT NULL,
        `questions_attempted` INT DEFAULT 0,
        `correct_answers` INT DEFAULT 0,
        `wrong_answers` INT DEFAULT 0,
        `time_taken_seconds` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_date` (`user_id`, `session_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 2. Vocabulary Tracking
    "vocab_progress" => "CREATE TABLE IF NOT EXISTS `vocab_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `words_learned` INT DEFAULT 0,
        `last_quiz_score` INT DEFAULT 0,
        `current_streak` INT DEFAULT 0,
        `last_active_date` DATE,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "vocab_bookmarks" => "CREATE TABLE IF NOT EXISTS `vocab_bookmarks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `word_id` INT NOT NULL,
        `bookmarked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_word` (`user_id`, `word_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 3. Chapter-wise MCQs/Questions Tracking
    "mcq_progress" => "CREATE TABLE IF NOT EXISTS `mcq_progress` (
        `attempt_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `subject_id` INT NOT NULL,
        `chapter_id` INT NOT NULL,
        `question_id` INT NOT NULL,
        `selected_option` VARCHAR(1) NOT NULL,
        `is_correct` BOOLEAN NOT NULL,
        `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_chapter` (`user_id`, `chapter_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "chapter_completion" => "CREATE TABLE IF NOT EXISTS `chapter_completion` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `chapter_id` INT NOT NULL,
        `completion_percentage` DECIMAL(5,2) DEFAULT 0.00,
        `last_accessed` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_chapter_completion` (`user_id`, `chapter_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 4. "Join Class" Feature Database
    "classrooms" => "CREATE TABLE IF NOT EXISTS `classrooms` (
        `class_id` INT AUTO_INCREMENT PRIMARY KEY,
        `teacher_id` INT NOT NULL,
        `class_code` VARCHAR(10) UNIQUE NOT NULL,
        `class_name` VARCHAR(100) NOT NULL,
        `board` ENUM('CBSE', 'State Board') NOT NULL,
        `medium` ENUM('Marathi', 'Semi-English', 'English') NOT NULL,
        `class_level` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "student_class_mapping" => "CREATE TABLE IF NOT EXISTS `student_class_mapping` (
        `mapping_id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `class_id` INT NOT NULL,
        `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_student_class` (`student_id`, `class_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($sql_chunks as $table_name => $sql) {
    echo "Creating table $table_name... ";
    try {
        $pdo->exec($sql);
        echo "✅ OK\n";
    } catch (PDOException $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

// --- SURGICAL REPAIR (Fixing users table to add profile metadata) ---
echo "\n=== EXTENDING USERS TABLE ===\n";

$repairs = [
    "users" => [
        "board" => "ALTER TABLE `users` ADD COLUMN `board` ENUM('CBSE', 'State Board') DEFAULT 'State Board'",
        "medium" => "ALTER TABLE `users` ADD COLUMN `medium` ENUM('Marathi', 'Semi-English', 'English') DEFAULT 'Marathi'",
        "class_level" => "ALTER TABLE `users` ADD COLUMN `class_level` INT"
    ]
];

foreach ($repairs as $table => $columns) {
    foreach ($columns as $col => $alter_sql) {
        // Check if column exists
        try {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
            if (!$check) {
                echo "   Adding missing column $col to $table... ";
                $pdo->exec($alter_sql);
                echo "✅ Fixed\n";
            } else {
                echo "   Column $col already exists in $table.\n";
            }
        } catch (Exception $e) {
            echo "❌ Fail updating $table.$col: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nAdvanced Schema sync completed successfully!";
?>
