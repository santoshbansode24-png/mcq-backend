<?php
/**
 * PDF-to-Exam Migration Script
 * Creates the necessary tables for background AI processing and temporary data storage.
 */

require_once '../config/db.php';

try {
    // 1. PDF Study Jobs Table
    // Tracks the processing state of each uploaded file.
    $sql1 = "CREATE TABLE IF NOT EXISTS `pdf_study_jobs` (
        `job_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `folder_id` INT DEFAULT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(512) NOT NULL,
        `pdf_base64` LONGTEXT DEFAULT NULL,
        `extracted_text` LONGTEXT DEFAULT NULL,
        `study_content` LONGTEXT DEFAULT NULL,
        `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        `progress` INT DEFAULT 0,
        `total_pages` INT DEFAULT 0,
        `processed_pages` INT DEFAULT 0,
        `error_message` TEXT,
        `difficulty` VARCHAR(32) DEFAULT 'mix',
        `total_chunks` INT DEFAULT 1,
        `last_processed_chunk` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`folder_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // 2. PDF Study Content Table
    // Stores the AI-generated study packs temporarily before they are synced to the phone.
    $sql2 = "CREATE TABLE IF NOT EXISTS `pdf_study_content` (
        `content_id` INT AUTO_INCREMENT PRIMARY KEY,
        `job_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `study_pack_json` LONGTEXT NOT NULL,
        `is_synced` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`job_id`) REFERENCES `pdf_study_jobs`(`job_id`) ON DELETE CASCADE,
        INDEX (`user_id`),
        INDEX (`is_synced`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    echo "Running migrations..." . PHP_EOL;
    $pdo->exec($sql1);
    echo "Table 'pdf_study_jobs' created/verified." . PHP_EOL;
    $pdo->exec($sql2);
    echo "Table 'pdf_study_content' created/verified." . PHP_EOL;

    echo "Migration complete! 🚀" . PHP_EOL;

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . PHP_EOL;
}
?>
