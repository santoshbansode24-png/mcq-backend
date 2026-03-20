-- FIX FOR MISSING AI TABLES ON RAILWAY
-- 1. Create system_settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Insert Default AI Limits (if missing)
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('ai_global_limit_daily', '100000');
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('ai_free_mode_enabled', '1');
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('ai_free_request_limit_daily', '10');

-- 3. Create ai_usage tracking table
CREATE TABLE IF NOT EXISTS `ai_usage` (
    `usage_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `usage_date` DATE NOT NULL,
    `tokens_used` INT DEFAULT 0,
    `request_count` INT DEFAULT 0,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_date` (`user_id`, `usage_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create index for performance
CREATE INDEX IF NOT EXISTS idx_ai_usage_date ON `ai_usage` (`usage_date`);
