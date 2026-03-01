<?php
/**
 * Verify OTP API
 * Verifies the OTP code entered by user (WhatsApp/Twilio-based)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';
require_once '../config/sms_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

try {
    // Get JSON input
    $input = getJsonInput();

    if (empty($input['otp_code'])) {
        sendResponse('error', 'OTP code is required', null, 400);
    }

    $identifier = '';
    if (!empty($input['email'])) {
        $identifier = trim($input['email']);
    } else if (!empty($input['mobile'])) {
        $identifier = trim($input['mobile']);
    } else if (!empty($input['phone'])) {
        $identifier = trim($input['phone']);
    } else {
        sendResponse('error', 'Email or Phone address is required', null, 400);
    }

    $identifier = strtolower($identifier);
    $otpCode = trim($input['otp_code']);

    // Find the user to get their phone number
    $stmt = $pdo->prepare("SELECT user_id, mobile, phone FROM users WHERE email = ? OR mobile = ? OR phone = ?");
    $stmt->execute([$identifier, $identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse('error', 'Invalid account', null, 404);
    }

    $userPhone = !empty($user['mobile']) ? $user['mobile'] : $user['phone'];

    if (empty($userPhone)) {
        sendResponse('error', 'No phone number associated with this account', null, 404);
    }

    // Find valid OTP
    $stmt = $pdo->prepare("
        SELECT id, user_id, verified 
        FROM password_reset_otps 
        WHERE phone_number = ? 
        AND otp_code = ? 
        AND expires_at > NOW() 
        AND verified = FALSE 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userPhone, $otpCode]);
    $otpRecord = $stmt->fetch();

    if (!$otpRecord) {
        sendResponse('error', 'Invalid or expired OTP code', null, 400);
    }

    // Mark OTP as verified
    $updateStmt = $pdo->prepare("UPDATE password_reset_otps SET verified = TRUE WHERE id = ?");
    $updateStmt->execute([$otpRecord['id']]);

    // Generate a secure reset token (valid for 15 minutes) and save it to DB
    $resetToken      = bin2hex(random_bytes(32));
    $tokenExpiresAt  = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $saveToken = $pdo->prepare("UPDATE password_reset_otps SET reset_token = ?, token_expires_at = ? WHERE id = ?");
    $saveToken->execute([$resetToken, $tokenExpiresAt, $otpRecord['id']]);


    // Get full user details
    $userStmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE user_id = ?");
    $userStmt->execute([$otpRecord['user_id']]);
    $userDetails = $userStmt->fetch();

    sendResponse('success', 'OTP verified successfully', [
        "reset_token"             => $resetToken,
        "user_id"                 => $userDetails['user_id'],
        "user_name"               => $userDetails['name'],
        "token_expires_in_minutes" => 15
    ], 200);

} catch (Exception $e) {
    error_log("Verify OTP Error: " . $e->getMessage());
    sendResponse('error', 'An error occurred. Please try again later.', ['debug' => $e->getMessage()], 500);
}
?>
