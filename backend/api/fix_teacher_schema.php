<?php
/**
 * Database Fixer for Teacher Portal
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
    echo "<p style='color: green'>✅ Table 'teacher_classes' ensured with class_code and division_name columns.</p>";

    // 1.5 Add board, medium, class_level to classrooms table if missing
    try {
        $pdo->exec("ALTER TABLE classrooms ADD COLUMN board VARCHAR(50) DEFAULT 'CBSE'");
        $pdo->exec("ALTER TABLE classrooms ADD COLUMN medium VARCHAR(50) DEFAULT 'English'");
        $pdo->exec("ALTER TABLE classrooms ADD COLUMN class_level INT DEFAULT 0");
        echo "<p style='color: green'>✅ Added board, medium, class_level to classrooms.</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange'>⚠️ Classrooms column check: " . $e->getMessage() . "</p>";
    }

    // 2. Add school_name to users table
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN school_name VARCHAR(255) DEFAULT NULL");
        echo "<p style='color: green'>✅ Column 'school_name' added to users.</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange'>⚠️ Column 'school_name' check: " . $e->getMessage() . "</p>";
    }

    // 3. Add mobile to users table
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN mobile VARCHAR(20) DEFAULT NULL");
        echo "<p style='color: green'>✅ Column 'mobile' added to users.</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange'>⚠️ Column 'mobile' check: " . $e->getMessage() . "</p>";
    }

    // 4. Add board_type to users table (if missing, used in registration)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN board_type VARCHAR(50) DEFAULT 'CBSE'");
        echo "<p style='color: green'>✅ Column 'board_type' added to users.</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange'>⚠️ Column 'board_type' check: " . $e->getMessage() . "</p>";
    }

    // 5. Create live_exams table
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

    // Ensure columns exist on live_exams table
    try {
        $pdo->exec("ALTER TABLE live_exams ADD COLUMN selected_mcq_ids TEXT DEFAULT NULL");
        echo "<p style='color: green'>✅ Column 'selected_mcq_ids' added/verified in live_exams.</p>";
    } catch (PDOException $e) {
        // Ignore if exists
    }
    try {
        $pdo->exec("ALTER TABLE live_exams ADD COLUMN selected_question_ids TEXT DEFAULT NULL");
        echo "<p style='color: green'>✅ Column 'selected_question_ids' added/verified in live_exams.</p>";
    } catch (PDOException $e) {
        // Ignore if exists
    }

    // 6. Create class_updates table (for sharing materials)
    $sql_updates = "CREATE TABLE IF NOT EXISTS class_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        school_name VARCHAR(255) DEFAULT NULL,
        class_id INT NOT NULL,
        update_type ENUM('announcement', 'homework', 'exam', 'material', 'worksheet', 'photo', 'pdf', 'live_class', 'live_exam') DEFAULT 'announcement',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        payload JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (class_id),
        INDEX (school_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_updates);
    echo "<p style='color: green'>✅ Table 'class_updates' ensured.</p>";

    // Ensure ENUM includes live_class and live_exam for existing tables
    try {
        $pdo->exec("ALTER TABLE class_updates MODIFY COLUMN update_type ENUM('announcement', 'homework', 'exam', 'material', 'worksheet', 'photo', 'pdf', 'live_class', 'live_exam') DEFAULT 'announcement'");
        echo "<p style='color: green'>✅ Column 'update_type' ENUM expanded to support live_class and live_exam.</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange'>⚠️ Column 'update_type' ENUM alter check: " . $e->getMessage() . "</p>";
    }

    echo "<p><strong>Fix complete! Please try logging in to the Teacher Portal again.</strong></p>";

} catch (PDOException $e) {
    echo "<p style='color: red'>❌ Critical Error: " . $e->getMessage() . "</p>";
}
?>
