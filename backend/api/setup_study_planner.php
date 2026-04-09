<?php
require_once 'cors_middleware.php';
/**
 * Setup Study Planner Tables
 * Creates the necessary tables for the Personal Study Planner
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$response = [];

try {
    // 1. Table: study_plans
    // Stores the student's preferences and current plan status
    // Added: goal_type, target_date for Advanced Features
    $sql_plans = "CREATE TABLE IF NOT EXISTS study_plans (
        plan_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        target_hours_per_day DECIMAL(3,1) DEFAULT 2.0,
        focus_subjects JSON,
        difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        goal_type ENUM('daily_habit', 'monthly_goal') DEFAULT 'daily_habit',
        target_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql_plans);
    
    // Auto-migration for existing tables (Add columns if they don't exist)
    try {
        $pdo->exec("ALTER TABLE study_plans ADD COLUMN goal_type ENUM('daily_habit', 'monthly_goal') DEFAULT 'daily_habit'");
    } catch (Exception $e) { /* Column likely exists */ }
    
    try {
        $pdo->exec("ALTER TABLE study_plans ADD COLUMN target_date DATE NULL");
    } catch (Exception $e) { /* Column likely exists */ }

    $response['study_plans'] = "Table 'study_plans' checked/updated.";

    // 2. Table: study_tasks
    // Individual daily missions generated for the student
    // Added: chapter_id to link to actual syllabus
    $sql_tasks = "CREATE TABLE IF NOT EXISTS study_tasks (
        task_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        plan_id INT,
        task_date DATE NOT NULL,
        subject VARCHAR(100) NOT NULL,
        chapter_id INT NULL,
        title VARCHAR(255) NOT NULL,
        task_type VARCHAR(50) NOT NULL,
        duration_minutes INT DEFAULT 15,
        status ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
        xp_reward INT DEFAULT 50,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (plan_id) REFERENCES study_plans(plan_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql_tasks);
    
    try {
        $pdo->exec("ALTER TABLE study_tasks MODIFY COLUMN task_type VARCHAR(50) NOT NULL");
    } catch (Exception $e) { /* Might fail if column is already updated or other issues */ }

    try {
        $pdo->exec("ALTER TABLE study_tasks ADD COLUMN chapter_id INT NULL");
    } catch (Exception $e) { /* Column likely exists */ }

    $response['study_tasks'] = "Table 'study_tasks' checked/updated.";

    // 3. Table: study_streaks
    // Gamification tracking (streaks and XP)
    $sql_streaks = "CREATE TABLE IF NOT EXISTS study_streaks (
        user_id INT PRIMARY KEY,
        current_streak INT DEFAULT 0,
        longest_streak INT DEFAULT 0,
        total_xp INT DEFAULT 0,
        last_active_date DATE NULL,
        level INT DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql_streaks);
    $response['study_streaks'] = "Table 'study_streaks' checked/created.";

    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Setup Failed: ' . $e->getMessage()
    ]);
}
?>
