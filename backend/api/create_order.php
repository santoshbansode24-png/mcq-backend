<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../vendor/autoload.php'; // Load Razorpay via Composer
if (file_exists('../config/secrets.php')) {
    require_once '../config/secrets.php';
}

if (!defined('RAZORPAY_KEY_ID')) {
    define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: '');
}
if (!defined('RAZORPAY_KEY_SECRET')) {
    define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: '');
}
require_once '../config/db.php';

use Razorpay\Api\Api;

try {
    // 1. Get Input
    $inputJSON = file_get_contents("php://input");
    $data = json_decode($inputJSON, true);

    $userId = $data['user_id'] ?? 0;
    $planId = $data['plan_id'] ?? 0;
    $couponCode = strtoupper(trim($data['coupon_code'] ?? ''));

    if ($userId <= 0 || $planId <= 0) {
        throw new Exception("Invalid User ID or Plan ID");
    }

    // Fetch the correct price for the plan_id from the subscriptions table
    $conn = $pdo;
    $planStmt = $conn->prepare("SELECT price FROM subscriptions WHERE plan_id = ? AND is_active = 1");
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception("Invalid or Inactive Plan Selected");
    }

    $amount = (float)$plan['price'];
    $appliedCouponId = null;

    if (!empty($couponCode)) {
        $cStmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
        $cStmt->execute([$couponCode]);
        $coupon = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) throw new Exception("Invalid or Inactive Coupon");
        if ($coupon['expiry_date'] && strtotime($coupon['expiry_date']) < time()) throw new Exception("Coupon has expired");
        if ($coupon['max_uses'] !== null && $coupon['current_uses'] >= $coupon['max_uses']) throw new Exception("Coupon usage limit reached");
        
        $chkStmt = $conn->prepare("SELECT id FROM coupon_usage WHERE coupon_id = ? AND user_id = ?");
        $chkStmt->execute([$coupon['coupon_id'], $userId]);
        if ($chkStmt->fetch()) throw new Exception("You have already used this coupon");

        if ($coupon['discount_type'] === 'percent') {
            $amount = $amount - ($amount * ((float)$coupon['discount_value'] / 100));
        } else {
            $amount = $amount - (float)$coupon['discount_value'];
        }
        $amount = max(0, $amount);
        $appliedCouponId = $coupon['coupon_id'];
    }

    if ($amount <= 0) {
        // Free order! Grant subscription instantly.
        $uStmt = $conn->prepare("SELECT subscription_expiry FROM users WHERE user_id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
        
        $current_expiry = $user['subscription_expiry'] ? strtotime($user['subscription_expiry']) : time();
        $days = (int)$plan['duration_days'];
        $new_expiry_time = max(time(), $current_expiry) + ($days * 24 * 60 * 60);
        $new_expiry = date('Y-m-d', $new_expiry_time);

        $conn->prepare("UPDATE users SET subscription_status = 'active', subscription_expiry = ? WHERE user_id = ?")->execute([$new_expiry, $userId]);
        
        if ($appliedCouponId) {
            $conn->prepare("INSERT INTO coupon_usage (coupon_id, user_id, order_id) VALUES (?, ?, 'FREE')")->execute([$appliedCouponId, $userId]);
            $conn->prepare("UPDATE coupons SET current_uses = current_uses + 1 WHERE coupon_id = ?")->execute([$appliedCouponId]);
        }

        echo json_encode([
            "status" => "success_free",
            "message" => "Subscription activated successfully!",
            "new_expiry" => $new_expiry
        ]);
        exit();
    }

    // 2. Initialize Razorpay
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    // 3. Create Order
    // Amount is in paice (100 paise = 1 Rupee). So 99 INR = 9900 paise.
    $paiseAmount = (int)round($amount * 100);
    if ($paiseAmount < 100) {
        throw new Exception("Order amount less than minimum amount allowed (Rs 1)");
    }
    
    $orderData = [
        'receipt'         => 'rcpt_' . $userId . '_' . time(),
        'amount'          => $paiseAmount, 
        'currency'        => 'INR',
        'payment_capture' => 1 // Auto capture
    ];

    $razorpayOrder = $api->order->create($orderData);
    $orderId = $razorpayOrder['id'];

    // 4. Save to Database (Status: Created)
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, plan_id, order_id, amount, status, coupon_id) VALUES (?, ?, ?, ?, 'created', ?)");
    $stmt->execute([$userId, $planId, $orderId, $amount, $appliedCouponId]);

    // 5. Return Order ID to Frontend
    echo json_encode([
        "status" => "success",
        "order_id" => $orderId,
        "key_id" => RAZORPAY_KEY_ID, // Send key context to frontend
        "amount" => $amount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
