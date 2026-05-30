<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

try {
    $conn = $pdo;

    // Create coupons table
    $conn->exec("CREATE TABLE IF NOT EXISTS `coupons` (
      `coupon_id` int(11) NOT NULL AUTO_INCREMENT,
      `code` varchar(50) NOT NULL,
      `discount_type` enum('percent', 'fixed') NOT NULL DEFAULT 'percent',
      `discount_value` decimal(10,2) NOT NULL,
      `max_uses` int(11) DEFAULT NULL,
      `current_uses` int(11) DEFAULT 0,
      `expiry_date` datetime DEFAULT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`coupon_id`),
      UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Create coupon_usage table
    $conn->exec("CREATE TABLE IF NOT EXISTS `coupon_usage` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `coupon_id` int(11) NOT NULL,
      `user_id` int(11) NOT NULL,
      `order_id` varchar(100) DEFAULT NULL,
      `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `coupon_id` (`coupon_id`),
      KEY `user_id` (`user_id`),
      FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`coupon_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Alter transactions table (ignore error if column already exists)
    try {
        $conn->exec("ALTER TABLE transactions ADD COLUMN plan_id INT DEFAULT NULL;");
    } catch (Exception $e) {}

    try {
        $conn->exec("ALTER TABLE transactions ADD COLUMN coupon_id INT DEFAULT NULL;");
    } catch (Exception $e) {}

    echo json_encode(["status" => "success", "message" => "Coupon tables and schema migrated successfully on Railway."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
