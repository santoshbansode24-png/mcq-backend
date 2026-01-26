ALTER TABLE `users`
ADD COLUMN `subscription_status` ENUM('free', 'active', 'expired') DEFAULT 'free',
ADD COLUMN `subscription_expiry` DATETIME NULL;
