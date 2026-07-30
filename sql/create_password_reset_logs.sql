-- Create password_reset_logs table for audit logging and rate limiting
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
