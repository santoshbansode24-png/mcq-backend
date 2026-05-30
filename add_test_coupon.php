<?php
require 'backend/config/db.php';
$pdo->query("INSERT IGNORE INTO coupons (code, discount_type, discount_value, max_uses, current_uses, is_active) VALUES ('FREE100', 'percent', 100, 1000, 0, 1)");
echo "Coupon added";
