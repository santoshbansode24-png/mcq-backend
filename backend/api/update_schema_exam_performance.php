<?php
/**
 * Exam Performance & Negative Marking Schema Updater
 * Veeru Backend
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== EXAM PERFORMANCE & NEGATIVE MARKING SCHEMA UPDATE ===\n\n";

$sql_chunks = [
    "exams" => "CREATE TABLE IF NOT EXISTS `exams` (
        `exam_id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `class_id` INT NOT NULL,
        `subject_id` INT DEFAULT NULL,
        `duration_minutes` INT DEFAULT 30,
        `positive_marks` DECIMAL(5,2) DEFAULT 4.00,
        `negative_marks` DECIMAL(5,2) DEFAULT 1.00,
        `total_questions` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`class_id`),
        INDEX (`subject_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "exam_attempts" => "CREATE TABLE IF NOT EXISTS `exam_attempts` (
        `attempt_id` INT AUTO_INCREMENT PRIMARY KEY,
        `exam_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `total_questions` INT NOT NULL DEFAULT 0,
        `correct_count` INT NOT NULL DEFAULT 0,
        `wrong_count` INT NOT NULL DEFAULT 0,
        `unattempted_count` INT NOT NULL DEFAULT 0,
        `positive_score` DECIMAL(7,2) NOT NULL DEFAULT 0.00,
        `negative_deduction` DECIMAL(7,2) NOT NULL DEFAULT 0.00,
        `net_score` DECIMAL(7,2) NOT NULL DEFAULT 0.00,
        `max_possible_score` DECIMAL(7,2) NOT NULL DEFAULT 0.00,
        `accuracy_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `time_spent_seconds` INT DEFAULT 0,
        `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`exam_id`),
        INDEX (`completed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "exam_attempt_answers" => "CREATE TABLE IF NOT EXISTS `exam_attempt_answers` (
        `answer_id` INT AUTO_INCREMENT PRIMARY KEY,
        `attempt_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `exam_id` INT NOT NULL,
        `mcq_id` INT NOT NULL,
        `chapter_id` INT DEFAULT NULL,
        `selected_option` VARCHAR(10) DEFAULT NULL,
        `correct_option` VARCHAR(10) NOT NULL,
        `is_correct` TINYINT(1) DEFAULT 0,
        `marks_awarded` DECIMAL(5,2) DEFAULT 0.00,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`attempt_id`),
        INDEX (`user_id`),
        INDEX (`mcq_id`),
        INDEX (`is_correct`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($sql_chunks as $table_name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ Table '{$table_name}' verified/created successfully.\n";
    } catch (PDOException $e) {
        echo "❌ Table '{$table_name}' error: " . $e->getMessage() . "\n";
    }
}

// Add positive_marks and negative_marks columns to existing exams table if missing
try {
    $stmtCols = $pdo->query("DESCRIBE `exams`");
    $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('positive_marks', $cols)) {
        $pdo->exec("ALTER TABLE `exams` ADD COLUMN `positive_marks` DECIMAL(5,2) DEFAULT 4.00");
        echo "✅ Added `positive_marks` column to `exams` table.\n";
    }
    if (!in_array('negative_marks', $cols)) {
        $pdo->exec("ALTER TABLE `exams` ADD COLUMN `negative_marks` DECIMAL(5,2) DEFAULT 1.00");
        echo "✅ Added `negative_marks` column to `exams` table.\n";
    }
} catch (PDOException $e) {
    echo "Note on `exams` columns: " . $e->getMessage() . "\n";
}

echo "\n=== EXAM SCHEMA MIGRATION COMPLETE ===\n";
