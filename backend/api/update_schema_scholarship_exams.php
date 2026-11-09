<?php
/**
 * Update Schema for Scholarship Live Exams
 * Self-healing schema migration
 * Veeru API
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=UTF-8');

$logs = [];

try {
    // 1. Ensure exams table has live scholarship exam columns
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `exams` (
            `exam_id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `class_id` INT NOT NULL,
            `subject_id` INT DEFAULT NULL,
            `duration_minutes` INT DEFAULT 60,
            `positive_marks` DECIMAL(5,2) DEFAULT 2.00,
            `negative_marks` DECIMAL(5,2) DEFAULT 0.00,
            `total_questions` INT DEFAULT 0,
            `is_live` TINYINT(1) DEFAULT 0,
            `is_scholarship` TINYINT(1) DEFAULT 1,
            `start_time` DATETIME DEFAULT NULL,
            `end_time` DATETIME DEFAULT NULL,
            `instructions` TEXT DEFAULT NULL,
            `question_ids` MEDIUMTEXT DEFAULT NULL,
            `selected_chapters` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`class_id`),
            INDEX (`is_live`),
            INDEX (`is_scholarship`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $logs[] = "exams table verified/created";

    // Check if columns exist in exams table, if not, alter table
    $existingCols = $pdo->query("SHOW COLUMNS FROM `exams`")->fetchAll(PDO::FETCH_COLUMN);

    $neededCols = [
        'is_live' => "ALTER TABLE `exams` ADD COLUMN `is_live` TINYINT(1) DEFAULT 0",
        'is_scholarship' => "ALTER TABLE `exams` ADD COLUMN `is_scholarship` TINYINT(1) DEFAULT 1",
        'start_time' => "ALTER TABLE `exams` ADD COLUMN `start_time` DATETIME DEFAULT NULL",
        'end_time' => "ALTER TABLE `exams` ADD COLUMN `end_time` DATETIME DEFAULT NULL",
        'instructions' => "ALTER TABLE `exams` ADD COLUMN `instructions` TEXT DEFAULT NULL",
        'question_ids' => "ALTER TABLE `exams` ADD COLUMN `question_ids` MEDIUMTEXT DEFAULT NULL",
        'selected_chapters' => "ALTER TABLE `exams` ADD COLUMN `selected_chapters` TEXT DEFAULT NULL"
    ];

    foreach ($neededCols as $col => $alterSql) {
        if (!in_array($col, $existingCols)) {
            try {
                $pdo->exec($alterSql);
                $logs[] = "Added column $col to exams";
            } catch (Exception $e) {
                $logs[] = "Column $col already exists or error: " . $e->getMessage();
            }
        }
    }

    // 2. Ensure exam_attempts has is_live and rank caching if needed
    $existingAttemptCols = $pdo->query("SHOW COLUMNS FROM `exam_attempts`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('time_spent_seconds', $existingAttemptCols)) {
        try {
            $pdo->exec("ALTER TABLE `exam_attempts` ADD COLUMN `time_spent_seconds` INT DEFAULT 0");
            $logs[] = "Added time_spent_seconds to exam_attempts";
        } catch (Exception $e) {}
    }

    echo json_encode(['status' => 'success', 'logs' => $logs]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'logs' => $logs]);
}
