<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

try {
    $conn = $pdo;

    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'subscription_status'");
    if ($check->rowCount() > 0) {
        echo json_encode(["status" => "success", "message" => "Column already exists."]);
        exit;
    }

    // Add Columns
    $sql = "ALTER TABLE `users`
            ADD COLUMN `subscription_status` ENUM('free', 'active', 'expired') DEFAULT 'free',
            ADD COLUMN `subscription_expiry` DATETIME NULL";
            
    $conn->exec($sql);
    
    // Create Transactions Table if not exists
    $sql2 = "CREATE TABLE IF NOT EXISTS `transactions` (
        `transaction_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `order_id` VARCHAR(100) NOT NULL,
        `payment_id` VARCHAR(100) DEFAULT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'INR',
        `status` ENUM('created', 'success', 'failed') DEFAULT 'created',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_razorpay_order (`order_id`)
    )";
    $conn->exec($sql2);

    echo json_encode(["status" => "success", "message" => "Migration successful."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
