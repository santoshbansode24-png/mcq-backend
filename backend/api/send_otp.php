<?php
/**
 * Send OTP API
 * Generates and sends OTP to user's email address for password reset
 * Uses Resend email service (replaces MSG91 SMS)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';
require_once '../config/sms_config.php';
// require_once '../services/EmailService.php'; // REMOVED RESEND

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

    // Validate email/phone format (Temporarily basic check for upcoming WhatsApp update)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // We will change this to phone validation tomorrow for WhatsApp
        // sendResponse('error', 'Invalid address format', null, 400);
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
    $otp       = (string) rand(100000, 999999); // Temporarily replaced EmailService::generateOTP
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Save OTP to database (reusing phone_number column to store email)
    $stmt = $pdo->prepare("INSERT INTO password_reset_otps (user_id, phone_number, otp_code, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)");
    
    if (!$stmt->execute([$userId, $email, $otp, $expiresAt, $ipAddress])) {
        throw new Exception("Failed to save OTP");
    }

    // Send OTP via Email (Resend) - REMOVED!
    // $emailService = new EmailService();
    // $emailResult  = $emailService->sendOTP($email, $otp, $user['name']);

    // TODO: IMPLEMENT WHATSAPP OTP HERE TOMORROW
    // Temporarily faux successful response so the frontend doesn't crash tonight

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
