<?php
/**
 * Send OTP API
 * Generates and sends OTP to user's WhatsApp via Twilio or Email (Gmail)
 * v2.2 - Accepts mobile/phone/email fields, handles Gmail and Twilio WhatsApp
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';
require_once '../config/sms_config.php';
require_once '../services/TwilioService.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

try {
    // Get JSON input
    $input = getJsonInput();

    $identifier = '';
    if (!empty($input['email'])) {
        $identifier = trim($input['email']);
    } else if (!empty($input['mobile'])) {
        $identifier = trim($input['mobile']);
    } else if (!empty($input['phone'])) {
        $identifier = trim($input['phone']);
    } else {
        sendResponse('error', 'Email or mobile number is required', null, 400);
    }

    $identifier = strtolower($identifier);

    // Scope search by user_type (default to 'student' if not specified)
    $user_type = !empty($input['user_type']) ? sanitizeInput($input['user_type']) : 'student';

    $isEmail = (strpos($identifier, '@') !== false);

    // Check if user exists (optimized queries for email vs phone number)
    if ($isEmail) {
        $stmt = $pdo->prepare("SELECT user_id, name, email, mobile, phone, phone_number FROM users WHERE LOWER(email) = LOWER(?) AND user_type = ?");
        $stmt->execute([$identifier, $user_type]);
    } else {
        // Clean input to check if it's a mobile number (handles +91, spaces, leading zero)
        $cleaned_digits = preg_replace('/[^0-9]/', '', $identifier);
        $mobile_search = $identifier;
        if (is_numeric($cleaned_digits) && strlen($cleaned_digits) >= 10) {
            $mobile_search = substr($cleaned_digits, -10);
        }

        $stmt = $pdo->prepare("SELECT user_id, name, email, mobile, phone, phone_number FROM users WHERE (RIGHT(mobile, 10) = ? OR RIGHT(phone, 10) = ? OR RIGHT(phone_number, 10) = ?) AND user_type = ?");
        $stmt->execute([$mobile_search, $mobile_search, $mobile_search, $user_type]);
    }
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse('error', 'No account found with this email or phone number', null, 404);
    }

    $userId = $user['user_id'];
    $isEmail = (strpos($identifier, '@') !== false);
    
    // Choose the database key to store the OTP
    $lookupKey = $isEmail ? $user['email'] : (!empty($user['mobile']) ? $user['mobile'] : (!empty($user['phone']) ? $user['phone'] : $user['phone_number']));
    
    if (empty($lookupKey)) {
        sendResponse('error', 'No contact information associated with this account.', null, 400);
    }

    // Rate limiting — max 3 OTP requests per hour
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM password_reset_otps WHERE phone_number = ? AND created_at > ?");
    $stmt->execute([$lookupKey, $oneHourAgo]);
    $rateLimit = $stmt->fetch();

    if ($rateLimit['count'] >= OTP_MAX_ATTEMPTS_PER_HOUR) {
        sendResponse('error', 'Too many OTP requests. Please try again after 1 hour.', null, 429);
    }

    // Generate OTP
    $otp       = (string) rand(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Save OTP to database
    $stmt = $pdo->prepare("INSERT INTO password_reset_otps (user_id, phone_number, otp_code, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)");
    
    if (!$stmt->execute([$userId, $lookupKey, $otp, $expiresAt, $ipAddress])) {
        throw new Exception("Failed to save OTP");
    }

    if ($isEmail) {
        // Send OTP via Email (Gmail)
        $subject = "Reset Your Password - Veeru";
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Veeru App <noreply@veeruapp.in>\r\n";
        
        $body = "
        <html>
        <head>
          <title>Reset Your Password - Veeru</title>
          <style>
            body { font-family: Arial, sans-serif; background-color: #f4f5f6; padding: 20px; }
            .card { background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
            .otp { font-size: 24px; font-weight: bold; color: #4f46e5; letter-spacing: 4px; text-align: center; margin: 20px 0; font-family: monospace; }
            .header { font-size: 20px; font-weight: bold; color: #1e293b; margin-bottom: 15px; }
          </style>
        </head>
        <body>
          <div class='card'>
            <div class='header'>Hi " . htmlspecialchars($user['name']) . ",</div>
            <p>You requested to reset your password. Use the verification code below to proceed:</p>
            <div class='otp'>$otp</div>
            <p>This code is valid for " . OTP_EXPIRY_MINUTES . " minutes. If you did not request this, you can safely ignore this email.</p>
          </div>
        </body>
        </html>
        ";

        // Log OTP locally for debugging
        error_log("[OTP DEBUG] Sent OTP $otp to Gmail: $lookupKey");
        
        // Native php mail
        @mail($lookupKey, $subject, $body, $headers);
        
        $maskedEmail = maskEmailAddress($lookupKey);
        sendResponse('success', "OTP sent to Gmail: " . $maskedEmail, [
            "expires_in_minutes" => OTP_EXPIRY_MINUTES,
            "user_name"         => $user['name']
        ], 200);

    } else {
        // Send OTP via Twilio WhatsApp
        $twilioService = new TwilioService();
        $whatsappResult = $twilioService->sendWhatsAppOTP($lookupKey, $otp, $user['name']);

        // Mask phone for response (e.g. +91 ****** 1234)
        $maskedPhone = substr($lookupKey, 0, 3) . '****' . substr($lookupKey, -4);

        sendResponse('success', "OTP sent to WhatsApp: " . $maskedPhone, [
            "expires_in_minutes" => OTP_EXPIRY_MINUTES,
            "user_name"         => $user['name']
        ], 200);
    }

} catch (Exception $e) {
    error_log("Send OTP Error: " . $e->getMessage());
    sendResponse('error', 'An error occurred. Please try again later.', ['debug' => $e->getMessage()], 500);
}

function maskEmailAddress($email) {
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        return $name[0] . '***@' . $domain;
    }
    return $name[0] . str_repeat('*', $len - 2) . $name[$len - 1] . '@' . $domain;
}
?>
