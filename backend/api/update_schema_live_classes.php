<?php
/**
 * Database Schema Update Script for Premium Live Classes
 */
require_once '../config/db.php';

header('Content-Type: text/plain');
echo "Database Schema Updater: Live Classes\n";
echo "=====================================\n\n";

function executeQuery($pdo, $sql, $successMsg) {
    try {
        $pdo->exec($sql);
        echo "✅ $successMsg\n";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// 1. Create live_class_attendance table
$sqlAttendance = "
CREATE TABLE IF NOT EXISTS live_class_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_update_id INT NOT NULL,
    student_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_class_update (class_update_id),
    KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
executeQuery($pdo, $sqlAttendance, "Created table 'live_class_attendance'.");

// 2. Create live_class_chat table
$sqlChat = "
CREATE TABLE IF NOT EXISTS live_class_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_update_id INT NOT NULL,
    student_id INT NOT NULL,
    student_name VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_class_update (class_update_id),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
executeQuery($pdo, $sqlChat, "Created table 'live_class_chat'.");

// 3. Create live_class_reactions table (to support live reaction triggers across other devices)
$sqlReactions = "
CREATE TABLE IF NOT EXISTS live_class_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_update_id INT NOT NULL,
    reaction_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_class_update (class_update_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
executeQuery($pdo, $sqlReactions, "Created table 'live_class_reactions'.");

echo "\nDone.\n";
?>
