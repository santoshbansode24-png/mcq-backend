-- Transactions Table for Razorpay (Clean Setup)
DROP TABLE IF EXISTS `transactions`;

CREATE TABLE `transactions` (
    `transaction_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `order_id` VARCHAR(100) NOT NULL, -- Razorpay Order ID
    `payment_id` VARCHAR(100) DEFAULT NULL, -- Razorpay Payment ID (after success)
    `amount` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'INR',
    `status` ENUM('created', 'success', 'failed') DEFAULT 'created',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
);

-- Index for fast lookup
CREATE INDEX idx_razorpay_order ON `transactions` (`order_id`);
CREATE INDEX idx_user_trans_log ON `transactions` (`user_id`);
