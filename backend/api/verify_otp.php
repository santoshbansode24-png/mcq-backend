<?php
/**
 * Verify OTP API
 * Verifies the OTP code entered by user (email-based)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';
require_once '../services/EmailService.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

try {
    // Get JSON input
    $input = getJsonInput();

    if (empty($input['email']) || empty($input['otp_code'])) {
        sendResponse('error', 'Email and OTP code are required', null, 400);
    }

    $email   = strtolower(trim($input['email']));
    $otpCode = trim($input['otp_code']);

    // Find valid OTP (phone_number column stores email now)
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
    $stmt->execute([$email, $otpCode]);
    $otpRecord = $stmt->fetch();

    if (!$otpRecord) {
        sendResponse('error', 'Invalid or expired OTP code', null, 400);
    }

    // Mark OTP as verified
    $stmt = $pdo->prepare("UPDATE password_reset_otps SET verified = TRUE WHERE id = ?");
    $stmt->execute([$otpRecord['id']]);

    // Generate a temporary reset token (valid for 15 minutes)
    $resetToken  = bin2hex(random_bytes(32));
    // We don't save reset_token in DB currently, it's just passed back to client
    // For production, you'd save this in users or a reset_tokens table.

    // Get user details
    $stmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE user_id = ?");
    $stmt->execute([$otpRecord['user_id']]);
    $user = $stmt->fetch();

    sendResponse('success', 'OTP verified successfully', [
        "reset_token"             => $resetToken,
        "user_id"                 => $user['user_id'],
        "user_name"               => $user['name'],
        "token_expires_in_minutes" => 15
    ], 200);

} catch (Exception $e) {
    error_log("Verify OTP Error: " . $e->getMessage());
    sendResponse('error', 'An error occurred. Please try again later.', ['debug' => $e->getMessage()], 500);
}
?>
