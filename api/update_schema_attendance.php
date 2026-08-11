<?php
/**
 * Veeru Attendance System Database Schema Migration
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    // 1. Create attendance_sessions table
    $sql1 = "CREATE TABLE IF NOT EXISTS `attendance_sessions` (
        `session_id` INT AUTO_INCREMENT PRIMARY KEY,
        `class_id` INT NOT NULL,
        `subject_id` INT DEFAULT NULL,
        `teacher_id` INT NOT NULL,
        `session_date` DATE NOT NULL,
        `total_students` INT DEFAULT 0,
        `present_count` INT DEFAULT 0,
        `absent_count` INT DEFAULT 0,
        `late_count` INT DEFAULT 0,
        `leave_count` INT DEFAULT 0,
        `remarks` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_class_date_subj` (`class_id`, `session_date`, `subject_id`),
        INDEX `idx_class_date` (`class_id`, `session_date`),
        INDEX `idx_teacher` (`teacher_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql1);

    // 2. Create attendance_records table
    $sql2 = "CREATE TABLE IF NOT EXISTS `attendance_records` (
        `record_id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_id` INT NOT NULL,
        `student_id` INT NOT NULL,
        `status` ENUM('P', 'A', 'L', 'E') NOT NULL DEFAULT 'P',
        `remarks` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_session_student` (`session_id`, `student_id`),
        INDEX `idx_student_session` (`student_id`, `session_id`),
        INDEX `idx_status` (`status`),
        FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions`(`session_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql2);

    echo json_encode([
        'success' => true,
        'message' => 'Attendance database schema updated successfully.'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
