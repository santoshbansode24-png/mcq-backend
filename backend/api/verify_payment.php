<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../vendor/autoload.php';
require_once '../config/secrets.php';
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

    // A. Update Transaction Status
    $stmt = $conn->prepare("UPDATE transactions SET payment_id = ?, status = 'success' WHERE order_id = ?");
    $stmt->execute([$razorpayPaymentId, $razorpayOrderId]);

    // B. Activate Subscription for User (Unlimited AI)
    // Set status to 'active' and maybe set expiry to +30 days (optional, here just active)
    $stmt2 = $conn->prepare("UPDATE users SET subscription_status = 'active', subscription_expiry = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE user_id = ?");
    $stmt2->execute([$userId]);

    echo json_encode([
        "status" => "success",
        "message" => "Payment Verified & Subscription Activated"
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
