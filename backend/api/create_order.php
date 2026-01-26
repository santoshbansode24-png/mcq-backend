<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../vendor/autoload.php'; // Load Razorpay via Composer
require_once '../config/secrets.php';
require_once '../config/db.php';

use Razorpay\Api\Api;

try {
    // 1. Get Input
    $inputJSON = file_get_contents("php://input");
    $data = json_decode($inputJSON, true);

    $userId = $data['user_id'] ?? 0;
    $amount = $data['amount'] ?? 99; // Default 99 INR

    if ($userId <= 0) {
        throw new Exception("Invalid User ID");
    }

    // 2. Initialize Razorpay
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    // 3. Create Order
    // Amount is in paice (100 paise = 1 Rupee). So 99 INR = 9900 paise.
    $orderData = [
        'receipt'         => 'rcpt_' . $userId . '_' . time(),
        'amount'          => $amount * 100, 
        'currency'        => 'INR',
        'payment_capture' => 1 // Auto capture
    ];

    $razorpayOrder = $api->order->create($orderData);
    $orderId = $razorpayOrder['id'];

    // 4. Save to Database (Status: Created)
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, order_id, amount, status) VALUES (?, ?, ?, 'created')");
    $stmt->execute([$userId, $orderId, $amount]);

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
