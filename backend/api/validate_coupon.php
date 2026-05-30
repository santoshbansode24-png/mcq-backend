<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/db.php';

try {
    $data = json_decode(file_get_contents("php://input"));
    $code = strtoupper(trim($data->code ?? ''));
    $user_id = $data->user_id ?? 0;

    if (empty($code) || empty($user_id)) {
        throw new Exception("Coupon code and user ID are required.");
    }

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        throw new Exception("Invalid or inactive coupon code.");
    }

    if ($coupon['expiry_date'] && strtotime($coupon['expiry_date']) < time()) {
        throw new Exception("This coupon code has expired.");
    }

    if ($coupon['max_uses'] !== null && $coupon['current_uses'] >= $coupon['max_uses']) {
        throw new Exception("This coupon code has reached its usage limit.");
    }

    // Check if user already used it
    $checkUsage = $pdo->prepare("SELECT id FROM coupon_usage WHERE coupon_id = ? AND user_id = ?");
    $checkUsage->execute([$coupon['coupon_id'], $user_id]);
    if ($checkUsage->fetch()) {
        throw new Exception("You have already used this coupon code.");
    }

    echo json_encode([
        'success' => true,
        'coupon_id' => $coupon['coupon_id'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => (float)$coupon['discount_value']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
