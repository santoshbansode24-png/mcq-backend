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
require_once '../config/secrets.php';

try {
    $data = json_decode(file_get_contents("php://input"));
    $email = $data->email ?? '';
    $days = intval($data->days ?? 365);
    $admin_secret = $data->admin_secret ?? '';

    if (empty($email) || empty($admin_secret)) {
        throw new Exception("Email and admin_secret are required.");
    }

    // In a real app, validate admin_secret against an env variable. 
    // Here we'll hardcode a simple check or rely on an env variable if available.
    $expected_secret = getenv('ADMIN_SECRET') ?: 'veeru_admin_2026';
    if ($admin_secret !== $expected_secret) {
        throw new Exception("Unauthorized.");
    }

    // Find student user
    $stmt = $pdo->prepare("SELECT user_id, subscription_expiry FROM users WHERE email = ? AND user_type = 'student'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found.");
    }

    // Calculate new expiry
    $current_expiry = $user['subscription_expiry'] ? strtotime($user['subscription_expiry']) : time();
    $new_expiry_time = max(time(), $current_expiry) + ($days * 24 * 60 * 60);
    $new_expiry = date('Y-m-d', $new_expiry_time);

    // Update user
    $updateStmt = $pdo->prepare("UPDATE users SET subscription_status = 'active', subscription_expiry = ? WHERE user_id = ?");
    $updateStmt->execute([$new_expiry, $user['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => "Successfully granted $days days of free access.",
        'new_expiry' => $new_expiry
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
