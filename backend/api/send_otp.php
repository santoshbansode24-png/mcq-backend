<?php
/**
 * Send OTP API
 * Generates and sends OTP to user's phone number for password reset
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/db.php';
require_once '../config/sms_config.php';
require_once '../services/SMSService.php';

try {
    // Get POST data
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->phone_number)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Phone number is required"
        ]);
        exit();
    }
    
    // Validate and format phone number
    $phoneNumber = SMSService::formatPhoneNumber($data->phone_number);
    
    if (!SMSService::validatePhoneNumber($phoneNumber)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid phone number format. Use +91XXXXXXXXXX or 10-digit number"
        ]);
        exit();
    }
    
    // Check if user exists with this phone number
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE phone_number = ?");
    $stmt->bind_param("s", $phoneNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "No account found with this phone number"
        ]);
        exit();
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    
    // Check rate limiting - max 3 OTP requests per hour
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM password_reset_otps WHERE phone_number = ? AND created_at > ?");
    $stmt->bind_param("ss", $phoneNumber, $oneHourAgo);
    $stmt->execute();
    $rateLimitResult = $stmt->get_result()->fetch_assoc();
    
    if ($rateLimitResult['count'] >= OTP_MAX_ATTEMPTS_PER_HOUR) {
        http_response_code(429);
        echo json_encode([
            "status" => "error",
            "message" => "Too many OTP requests. Please try again after 1 hour."
        ]);
        exit();
    }
    
    // Generate OTP
    $otp = SMSService::generateOTP(OTP_LENGTH);
    
    // Calculate expiry time
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    
    // Get client IP
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Save OTP to database
    $stmt = $conn->prepare("INSERT INTO password_reset_otps (user_id, phone_number, otp_code, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $phoneNumber, $otp, $expiresAt, $ipAddress);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to save OTP");
    }
    
    // Send OTP via SMS
    $smsService = new SMSService();
    $smsResult = $smsService->sendOTP($phoneNumber, $otp);
    
    if (!$smsResult['success']) {
        // Delete the OTP record if SMS failed
        $stmt = $conn->prepare("DELETE FROM password_reset_otps WHERE user_id = ? AND otp_code = ?");
        $stmt->bind_param("is", $userId, $otp);
        $stmt->execute();
        
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => $smsResult['message']
        ]);
        exit();
    }
    
    // Success response
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "OTP sent successfully to " . substr($phoneNumber, 0, -4) . "XXXX",
        "expires_in_minutes" => OTP_EXPIRY_MINUTES,
        "user_name" => $user['name']
    ]);
    
} catch (Exception $e) {
    error_log("Send OTP Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred. Please try again later."
    ]);
}
?>
