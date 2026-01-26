-- AI Token Management Schema

-- 1. System Settings (For Global Limits)
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Default Global Limit (Start High: 50,000 tokens/day)
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('ai_global_limit_daily', '50000');
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('ai_free_mode_enabled', '1'); -- 1 = On, 0 = Off

-- 2. AI Usage Tracking (Per User, Per Day)
CREATE TABLE IF NOT EXISTS `ai_usage` (
    `usage_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `usage_date` DATE NOT NULL,
    `tokens_used` INT DEFAULT 0,
    `request_count` INT DEFAULT 0,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_date` (`user_id`, `usage_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
);

-- Index for fast lookups
CREATE INDEX idx_ai_usage_date ON `ai_usage` (`usage_date`);
