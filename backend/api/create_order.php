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

    // 2. Initialize Razorpay
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    // 3. Create Order
    // Amount is in paice (100 paise = 1 Rupee). So 99 INR = 9900 paise.
    $paiseAmount = (int)round($amount * 100);
    $orderData = [
        'receipt'         => 'rcpt_' . $userId . '_' . time(),
        'amount'          => $paiseAmount, 
        'currency'        => 'INR',
        'payment_capture' => 1 // Auto capture
    ];

    $razorpayOrder = $api->order->create($orderData);
    $orderId = $razorpayOrder['id'];

    // 4. Save to Database (Status: Created)
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, plan_id, order_id, amount, status) VALUES (?, ?, ?, ?, 'created')");
    $stmt->execute([$userId, $planId, $orderId, $amount]);

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
