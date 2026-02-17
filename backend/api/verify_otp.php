<?php
/**
 * Verify OTP API
 * Verifies the OTP code entered by user
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
    
    if (empty($data->phone_number) || empty($data->otp_code)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Phone number and OTP code are required"
        ]);
        exit();
    }
    
    // Format phone number
    $phoneNumber = SMSService::formatPhoneNumber($data->phone_number);
    $otpCode = trim($data->otp_code);
    
    // Find valid OTP
    $stmt = $conn->prepare("
        SELECT id, user_id, verified 
        FROM password_reset_otps 
        WHERE phone_number = ? 
        AND otp_code = ? 
        AND expires_at > NOW() 
        AND verified = FALSE
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("ss", $phoneNumber, $otpCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid or expired OTP code"
        ]);
        exit();
    }
    
    $otpRecord = $result->fetch_assoc();
    
    // Mark OTP as verified
    $stmt = $conn->prepare("UPDATE password_reset_otps SET verified = TRUE WHERE id = ?");
    $stmt->bind_param("i", $otpRecord['id']);
    $stmt->execute();
    
    // Generate a temporary reset token (valid for 15 minutes)
    $resetToken = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Store reset token in session or return to client
    // For simplicity, we'll return it to client
    
    // Get user details
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $otpRecord['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Success response
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "OTP verified successfully",
        "reset_token" => $resetToken,
        "user_id" => $user['id'],
        "user_name" => $user['name'],
        "token_expires_in_minutes" => 15
    ]);
    
} catch (Exception $e) {
    error_log("Verify OTP Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred. Please try again later."
    ]);
}
?>
