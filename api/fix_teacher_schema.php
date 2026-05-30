<?php
/**
 * Database Fixer for Teacher Portal (Root API version)
 * This script ensures all necessary tables and columns exist for the Teacher App.
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

echo "<h2>Fixing Teacher Portal Database Schema...</h2>";

try {
    // 1. Create teacher_classes table
    $sql = "CREATE TABLE IF NOT EXISTS teacher_classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        class_id INT NOT NULL,
        class_code VARCHAR(10) DEFAULT NULL,
        division_name VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_teacher (teacher_id),
        INDEX idx_class_code (class_code),
        FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "<p style='color: green'>✅ Table 'teacher_classes' ensured.</p>";

    // Helper function to safely add columns
    function addColumn($pdo, $table, $column, $definition) {
        try {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            echo "<p style='color: green'>✅ Column '$column' added to $table.</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<p style='color: orange'>⚠️ Column '$column' already exists in $table.</p>";
            } else {
                echo "<p style='color: red'>❌ Error adding $column: " . $e->getMessage() . "</p>";
            }
        }
    }

    // 2. Fix users table
    addColumn($pdo, 'users', 'school_name', 'VARCHAR(255) DEFAULT NULL');
    addColumn($pdo, 'users', 'mobile', 'VARCHAR(20) DEFAULT NULL');
    addColumn($pdo, 'users', 'phone', 'VARCHAR(20) DEFAULT NULL');
    addColumn($pdo, 'users', 'board_type', "VARCHAR(50) DEFAULT 'CBSE'");

    // 3. Create live_exams table
    $sql_exams = "CREATE TABLE IF NOT EXISTS live_exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        class_id INT NOT NULL,
        chapter_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        duration_minutes INT NOT NULL DEFAULT 15,
        status ENUM('active', 'completed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (class_id),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_exams);
    echo "<p style='color: green'>✅ Table 'live_exams' ensured.</p>";

    // Add missing column to live_exams if it doesn't exist
    addColumn($pdo, 'live_exams', 'selected_question_ids', 'LONGTEXT DEFAULT NULL');

    // 4. Create class_updates table
    $sql_updates = "CREATE TABLE IF NOT EXISTS class_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        school_name VARCHAR(255) DEFAULT NULL,
        class_id INT NOT NULL,
        update_type ENUM('announcement', 'homework', 'exam', 'material', 'worksheet', 'photo', 'pdf') DEFAULT 'announcement',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        payload JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (class_id),
        INDEX (school_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_updates);
    echo "<p style='color: green'>✅ Table 'class_updates' ensured.</p>";

    // 5. Create notifications table if missing (used for stats in login)
    $sql_notif = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        class_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (teacher_id),
        INDEX (class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_notif);
    echo "<p style='color: green'>✅ Table 'notifications' ensured.</p>";

    // Run performance optimizations
    require_once 'optimize_db.php';

    echo "<p><strong>Fix complete! Please try logging in to the Teacher Portal again.</strong></p>";

} catch (PDOException $e) {
    echo "<p style='color: red'>❌ Critical Error: " . $e->getMessage() . "</p>";
}
?>
