<?php
/**
 * Forgot Password API (Pin-Based Self Reset with Rate Limiting & Audit Logging)
 * Veeru
 * 
 * Endpoint: POST /api/forgot_password.php
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

$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Helper function to log reset attempts
function logResetAttempt($pdo, $user_id, $email, $mobile, $ip_address, $user_agent, $status, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_logs (user_id, email, mobile, ip_address, user_agent, status, message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $email, $mobile, $ip_address, $user_agent, $status, $message]);
    } catch (Exception $e) {
        error_log("Failed to insert password_reset_logs: " . $e->getMessage());
    }
}

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
    // Rate Limiting: Max 5 failed attempts per IP or Email in the last hour
    $rateLimitStmt = $pdo->prepare("
        SELECT COUNT(*) FROM password_reset_logs 
        WHERE (ip_address = ? OR LOWER(email) = LOWER(?)) 
          AND status IN ('failed_pin', 'failed_user') 
          AND created_at >= NOW() - INTERVAL 1 HOUR
    ");
    $rateLimitStmt->execute([$ip_address, $email]);
    $failedAttempts = $rateLimitStmt->fetchColumn();

    if ($failedAttempts >= 5) {
        logResetAttempt($pdo, null, $email, $mobile, $ip_address, $user_agent, 'rate_limited', 'Rate limit exceeded: too many failed attempts.');
        sendResponse('error', 'Too many failed password reset attempts. Please wait 1 hour before trying again or contact your Admin.', null, 429);
    }

    // 1. Verify user identity against Mobile and Email
    $stmt = $pdo->prepare("
        SELECT user_id, name, security_pin, mobile, phone 
        FROM users 
        WHERE LOWER(email) = LOWER(?) 
          AND (
            RIGHT(mobile, 10) = RIGHT(?, 10) 
            OR RIGHT(phone, 10) = RIGHT(?, 10) 
            OR RIGHT(phone_number, 10) = RIGHT(?, 10)
            OR mobile IS NULL OR mobile = ''
          )
    ");
    $stmt->execute([$email, $mobile, $mobile, $mobile]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        logResetAttempt($pdo, null, $email, $mobile, $ip_address, $user_agent, 'failed_user', 'No account found matching Mobile and Email.');
        sendResponse('error', 'No account found matching this Mobile Number and Email ID.', null, 404);
    }

    // 2. Verify Security PIN (Auto-initialize PIN if user had no PIN configured previously)
    if (empty($user['security_pin'])) {
        // Legacy user with no PIN set: auto-assign the PIN provided in this reset request
        $user['security_pin'] = $security_pin;
    }

    if ($user['security_pin'] !== $security_pin) {
        logResetAttempt($pdo, $user['user_id'], $email, $mobile, $ip_address, $user_agent, 'failed_pin', 'Incorrect 4-digit Security PIN.');
        sendResponse('error', 'Incorrect 4-digit Security PIN. If you forgot your PIN, please contact your Teacher or Admin.', null, 401);
    }

    // 3. Update Password, Security PIN & Timestamps
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $userMobile = !empty($user['mobile']) ? $user['mobile'] : $mobile;

    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET password = ?, 
            mobile = ?, 
            security_pin = ?,
            password_changed_at = NOW(), 
            updated_at = NOW() 
        WHERE user_id = ?
    ");
    $updateStmt->execute([$hashed_password, $userMobile, $security_pin, $user['user_id']]);

    // Log Success
    logResetAttempt($pdo, $user['user_id'], $email, $mobile, $ip_address, $user_agent, 'success', 'Password reset successfully.');

    sendResponse('success', 'Password updated successfully! You can now log in with your new password.', [
        'user_id' => $user['user_id']
    ], 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred: ' . $e->getMessage(), null, 500);
}
?>
