<?php
/**
 * Forgot Password API (Pin-Based Self Reset)
 * Veeru
 * 
 * Endpoint: POST /api/forgot_password.php
 * Accepts JSON or POST fields:
 * - mobile / phone
 * - email
 * - security_pin (4 digits)
 * - new_password
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Support both JSON input and standard form POST
$input = getJsonInput() ?: $_POST;

$email = isset($input['email']) ? trim($input['email']) : '';
$mobile = isset($input['mobile']) ? trim($input['mobile']) : (isset($input['phone']) ? trim($input['phone']) : (isset($input['phone_number']) ? trim($input['phone_number']) : ''));
$security_pin = isset($input['security_pin']) ? trim($input['security_pin']) : '';
$new_password = isset($input['new_password']) ? trim($input['new_password']) : '';

// Validation
if (empty($email) || empty($mobile) || empty($security_pin) || empty($new_password)) {
    sendResponse('error', 'All fields are required: email, mobile, 4-digit security_pin, and new_password.', null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email address format.', null, 400);
}

if (!preg_match('/^\d{4}$/', $security_pin)) {
    sendResponse('error', 'Security PIN must be exactly 4 digits.', null, 400);
}

if (strlen($new_password) < 6) {
    sendResponse('error', 'New password must be at least 6 characters long.', null, 400);
}

try {
    // 1. Verify user identity against Mobile, Email, and 4-Digit Security PIN
    $stmt = $pdo->prepare("
        SELECT user_id, name, security_pin 
        FROM users 
        WHERE LOWER(email) = LOWER(?) 
          AND (mobile = ? OR phone = ? OR phone_number = ?)
    ");
    $stmt->execute([$email, $mobile, $mobile, $mobile]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse('error', 'No account found matching this Mobile Number and Email ID.', null, 404);
    }

    // 2. Verify Security PIN
    if (empty($user['security_pin'])) {
        sendResponse('error', 'No Security PIN is set for your account yet. Please contact your Teacher or Admin to reset your password.', [
            'requires_admin_reset' => true
        ], 403);
    }

    if ($user['security_pin'] !== $security_pin) {
        sendResponse('error', 'Incorrect 4-digit Security PIN. If you forgot your PIN, please contact your Teacher or Admin.', null, 401);
    }

    // 3. Update Password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $updateStmt->execute([$hashed_password, $user['user_id']]);

    sendResponse('success', 'Password updated successfully! You can now log in with your new password.', [
        'user_id' => $user['user_id']
    ], 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred: ' . $e->getMessage(), null, 500);
}
?>
