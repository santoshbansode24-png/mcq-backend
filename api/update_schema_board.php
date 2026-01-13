<?php
/**
 * Update Schema for Board Support
 * Adds board_type column to classes and users tables
 */
require_once '../config/db.php';

try {
    // 1. Add board_type to classes table
    $check = $pdo->query("SHOW COLUMNS FROM classes LIKE 'board_type'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE classes ADD COLUMN board_type VARCHAR(50) DEFAULT 'STATE_MARATHI' AFTER class_name");
        echo "✅ Added board_type to classes table.<br>";
    } else {
        echo "ℹ️ board_type already exists in classes table.<br>";
    }

    // 2. Add board_type to users table
    $checkUser = $pdo->query("SHOW COLUMNS FROM users LIKE 'board_type'");
    if ($checkUser->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN board_type VARCHAR(50) DEFAULT NULL AFTER class_id");
        echo "✅ Added board_type to users table.<br>";
    } else {
        echo "ℹ️ board_type already exists in users table.<br>";
    }

    echo "🎉 Database schema updated successfully!";

} catch (PDOException $e) {
    echo "❌ Error updating schema: " . $e->getMessage();
}
?>
