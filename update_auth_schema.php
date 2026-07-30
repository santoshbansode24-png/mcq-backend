<?php
/**
 * Auth & Password Reset Schema Migration Script
 * Veeru
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

try {
    $messages = [];

    // 1. Check & Add updated_at column to users table
    $checkUpdatedAt = $pdo->query("SHOW COLUMNS FROM users LIKE 'updated_at'");
    if ($checkUpdatedAt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        $messages[] = "Added 'updated_at' column to users table.";
    } else {
        $messages[] = "'updated_at' column already exists in users table.";
    }

    // 2. Check & Add password_changed_at column to users table
    $checkPwdChanged = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_changed_at'");
    if ($checkPwdChanged->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_changed_at DATETIME DEFAULT NULL AFTER password");
        $messages[] = "Added 'password_changed_at' column to users table.";
    } else {
        $messages[] = "'password_changed_at' column already exists in users table.";
    }

    // 3. Create password_reset_logs table
    $createLogsTable = "
        CREATE TABLE IF NOT EXISTS `password_reset_logs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT DEFAULT NULL,
          `email` VARCHAR(150) NOT NULL,
          `mobile` VARCHAR(20) DEFAULT NULL,
          `ip_address` VARCHAR(45) NOT NULL,
          `user_agent` TEXT DEFAULT NULL,
          `status` ENUM('success', 'failed_pin', 'failed_user', 'rate_limited') NOT NULL,
          `message` VARCHAR(255) DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_user_id (`user_id`),
          INDEX idx_email (`email`),
          INDEX idx_ip (`ip_address`),
          INDEX idx_created_at (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createLogsTable);
    $messages[] = "Ensured 'password_reset_logs' table exists.";

    echo json_encode([
        'status' => 'success',
        'messages' => $messages
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Migration failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
