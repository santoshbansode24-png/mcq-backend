<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/db.php';

try {
    $inputJSON = file_get_contents("php://input");
    $data = json_decode($inputJSON, true);

    $couponCode = strtoupper(trim($data['coupon_code'] ?? ''));
    $userId = $data['user_id'] ?? 0;
    $planId = $data['plan_id'] ?? 0;

    if (empty($couponCode) || $userId <= 0 || $planId <= 0) {
        throw new Exception("Missing parameters");
    }

    $conn = $pdo;

    // 1. Get Plan Price
    $planStmt = $conn->prepare("SELECT price FROM subscriptions WHERE plan_id = ? AND is_active = 1");
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception("Invalid plan");
    }

    $amount = (float)$plan['price'];

    // 2. Validate Coupon
    $cStmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $cStmt->execute([$couponCode]);
    $coupon = $cStmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) throw new Exception("Invalid or inactive coupon");
    if ($coupon['expiry_date'] && strtotime($coupon['expiry_date']) < time()) throw new Exception("Coupon has expired");
    if ($coupon['max_uses'] !== null && $coupon['current_uses'] >= $coupon['max_uses']) throw new Exception("Coupon usage limit reached");
    
    $chkStmt = $conn->prepare("SELECT id FROM coupon_usage WHERE coupon_id = ? AND user_id = ?");
    $chkStmt->execute([$coupon['coupon_id'], $userId]);
    if ($chkStmt->fetch()) throw new Exception("You have already used this coupon");

    // 3. Calculate Discount
    $discountAmount = 0;
    if ($coupon['discount_type'] === 'percent') {
        $discountAmount = $amount * ((float)$coupon['discount_value'] / 100);
    } else {
        $discountAmount = (float)$coupon['discount_value'];
    }

    $finalAmount = max(0, $amount - $discountAmount);

    echo json_encode([
        "status" => "success",
        "message" => "Coupon applied successfully",
        "original_price" => $amount,
        "discount_amount" => $discountAmount,
        "final_price" => $finalAmount
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
