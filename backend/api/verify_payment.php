<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (file_exists('../config/secrets.php')) {
    require_once '../config/secrets.php';
}

if (!defined('RAZORPAY_KEY_SECRET')) {
    define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: '');
}
require_once '../config/db.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

try {
    $inputJSON = file_get_contents("php://input");
    $data = json_decode($inputJSON, true);

    $razorpayOrderId = $data['razorpay_order_id'];
    $razorpayPaymentId = $data['razorpay_payment_id'];
    $razorpaySignature = $data['razorpay_signature'];
    $userId = $data['user_id'];

    if (empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
        throw new Exception("Missing Payment Details");
    }

    // 1. Verify Signature
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    
    $attributes = [
        'razorpay_order_id' => $razorpayOrderId,
        'razorpay_payment_id' => $razorpayPaymentId,
        'razorpay_signature' => $razorpaySignature
    ];

    try {
        $api->utility->verifyPaymentSignature($attributes);
    } catch (SignatureVerificationError $e) {
        throw new Exception("Payment Signature Verification Failed!");
    }

    // 2. Signature Valid -> Update Database
    $conn = $pdo;

    // A. Verify the transaction and get plan_id
    $txStmt = $conn->prepare("SELECT plan_id, coupon_id FROM transactions WHERE order_id = ? AND status = 'created'");
    $txStmt->execute([$razorpayOrderId]);
    $transaction = $txStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception("Transaction not found or already processed.");
    }
    
    $planId = $transaction['plan_id'];
    $couponId = $transaction['coupon_id'];

    // B. Fetch plan duration
    $planStmt = $conn->prepare("SELECT duration_days FROM subscriptions WHERE plan_id = ?");
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
    $durationDays = $plan ? (int)$plan['duration_days'] : 30; // Fallback to 30 days if somehow missing

    // C. Update Transaction Status
    $stmt = $conn->prepare("UPDATE transactions SET payment_id = ?, status = 'success' WHERE order_id = ?");
    $stmt->execute([$razorpayPaymentId, $razorpayOrderId]);

    // D. Activate Subscription for User (Dynamic Expiry)
    // If the user already has an active subscription in the future, append to it. Otherwise, start from NOW().
    $stmt2 = $conn->prepare("
        UPDATE users 
        SET subscription_status = 'active', 
            subscription_expiry = CASE 
                WHEN subscription_expiry > NOW() THEN DATE_ADD(subscription_expiry, INTERVAL ? DAY)
                ELSE DATE_ADD(NOW(), INTERVAL ? DAY)
            END 
        WHERE user_id = ?
    ");
    $stmt2->execute([$durationDays, $durationDays, $userId]);
    
    // E. Mark Coupon as used if applicable
    if (!empty($couponId)) {
        $conn->prepare("INSERT INTO coupon_usage (coupon_id, user_id, order_id) VALUES (?, ?, ?)")->execute([$couponId, $userId, $razorpayOrderId]);
        $conn->prepare("UPDATE coupons SET current_uses = current_uses + 1 WHERE coupon_id = ?")->execute([$couponId]);
    }

    echo json_encode([
        "status" => "success",
        "message" => "Payment Verified & Subscription Activated for " . $durationDays . " days"
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
