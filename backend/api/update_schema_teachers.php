<?php
/**
 * Setup Teacher Schema API
 * 
 * Endpoint: GET /api/update_schema_teachers.php
 * Purpose: Creates necessary tables for the Teacher PWA and Class Updates feature.
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

try {
    // 1. Teachers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        school_name VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. Teacher Classes (linking table)
    $pdo->exec("CREATE TABLE IF NOT EXISTS teacher_classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        class_id INT NOT NULL,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        UNIQUE KEY unique_teacher_class (teacher_id, class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Class Updates Table (for the student feed)
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_updates (
        update_id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        school_name VARCHAR(255) NOT NULL,
        class_id INT NOT NULL,
        update_type ENUM('homework', 'exam', 'worksheet', 'photo', 'pdf', 'announcement', 'live_class', 'live_exam') NOT NULL DEFAULT 'announcement',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        payload JSON,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_updates_school_class (school_name, class_id),
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure ENUM includes live_class and live_exam for existing tables
    try {
        $pdo->exec("ALTER TABLE class_updates MODIFY COLUMN update_type ENUM('announcement', 'homework', 'exam', 'material', 'worksheet', 'photo', 'pdf', 'live_class', 'live_exam') DEFAULT 'announcement'");
    } catch (PDOException $e) {
        // Ignore if alter fails
    }

    sendResponse('success', 'Teacher schema updated successfully.', null, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
