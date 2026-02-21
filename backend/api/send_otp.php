<?php
/**
 * Send OTP API
 * Generates and sends OTP to user's email address for password reset
 * Uses Resend email service (replaces MSG91 SMS)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';
require_once '../config/sms_config.php';
require_once '../services/EmailService.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

try {
    // Get JSON input
    $input = getJsonInput();

    if (empty($input['email'])) {
        sendResponse('error', 'Email address is required', null, 400);
    }

    $email = strtolower(trim($input['email']));

    // Validate email format
    if (!EmailService::validateEmail($email)) {
        sendResponse('error', 'Invalid email address format', null, 400);
    }

    // Check if user exists with this email
    $stmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse('error', 'No account found with this email address', null, 404);
    }

    $userId = $user['user_id'];

    // Rate limiting — max 3 OTP requests per hour
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM password_reset_otps WHERE phone_number = ? AND created_at > ?");
    $stmt->execute([$email, $oneHourAgo]);
    $rateLimit = $stmt->fetch();

    if ($rateLimit['count'] >= OTP_MAX_ATTEMPTS_PER_HOUR) {
        sendResponse('error', 'Too many OTP requests. Please try again after 1 hour.', null, 429);
    }

    // Generate OTP
    $otp       = EmailService::generateOTP(OTP_LENGTH);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Save OTP to database (reusing phone_number column to store email)
    $stmt = $pdo->prepare("INSERT INTO password_reset_otps (user_id, phone_number, otp_code, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)");
    
    if (!$stmt->execute([$userId, $email, $otp, $expiresAt, $ipAddress])) {
        throw new Exception("Failed to save OTP");
    }

    // Send OTP via Email (Resend)
    $emailService = new EmailService();
    $emailResult  = $emailService->sendOTP($email, $otp, $user['name']);

    if (!$emailResult['success']) {
        // Delete the OTP record if email failed
        $stmt = $pdo->prepare("DELETE FROM password_reset_otps WHERE user_id = ? AND otp_code = ?");
        $stmt->execute([$userId, $otp]);

        sendResponse('error', $emailResult['message'], $emailResult['error'] ?? null, 500);
    }

    // Mask email for response (e.g. s***@gmail.com)
    $emailParts   = explode('@', $email);
    $maskedEmail  = substr($emailParts[0], 0, 1) . '***@' . $emailParts[1];

    sendResponse('success', "OTP sent to " . $maskedEmail, [
        "expires_in_minutes" => OTP_EXPIRY_MINUTES,
        "user_name"         => $user['name']
    ], 200);

} catch (Exception $e) {
    error_log("Send OTP Error: " . $e->getMessage());
    sendResponse('error', 'An error occurred. Please try again later.', ['debug' => $e->getMessage()], 500);
}
?>
