<?php
/**
 * Reset Password API
 * Resets user password after OTP verification
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

try {
    // Get POST data
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->user_id) || empty($data->new_password)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "User ID and new password are required"
        ]);
        exit();
    }
    
    $userId = intval($data->user_id);
    $newPassword = trim($data->new_password);
    
    // Validate password strength
    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Password must be at least 6 characters long"
        ]);
        exit();
    }
    
    // Optional: Check if there's a recent verified OTP for this user
    $stmt = $conn->prepare("
        SELECT id 
        FROM password_reset_otps 
        WHERE user_id = ? 
        AND verified = TRUE 
        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid or expired reset session. Please request a new OTP."
        ]);
        exit();
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update user password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update password");
    }
    
    // Delete all OTP records for this user (cleanup)
    $stmt = $conn->prepare("DELETE FROM password_reset_otps WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Get user details
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Success response
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Password reset successfully",
        "user_name" => $user['name']
    ]);
    
} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred. Please try again later."
    ]);
}
?>
