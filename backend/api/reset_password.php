<?php
/**
 * Reset Password API
 * Resets user password after OTP verification
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

try {
    // Get JSON input
    $input = getJsonInput();
    
    if (empty($input['user_id']) || empty($input['new_password'])) {
        sendResponse('error', 'User ID and new password are required', null, 400);
    }
    
    $userId = intval($input['user_id']);
    $newPassword = trim($input['new_password']);
    
    // Validate password strength
    if (strlen($newPassword) < 6) {
        sendResponse('error', 'Password must be at least 6 characters long', null, 400);
    }
    
    // Optional: Check if there's a recent verified OTP for this user
    $stmt = $pdo->prepare("
        SELECT id 
        FROM password_reset_otps 
        WHERE user_id = ? 
        AND verified = TRUE 
        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $otpRecord = $stmt->fetch();
    
    if (!$otpRecord) {
        sendResponse('error', 'Invalid or expired reset session. Please request a new OTP.', null, 403);
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update user password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    
    if (!$stmt->execute([$hashedPassword, $userId])) {
        throw new Exception("Failed to update password");
    }
    
    // Delete all OTP records for this user (cleanup)
    $stmt = $pdo->prepare("DELETE FROM password_reset_otps WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Get user details
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    sendResponse('success', 'Password reset successfully', [
        "user_name" => $user['name']
    ], 200);
    
} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    sendResponse('error', 'An error occurred. Please try again later.', ['debug' => $e->getMessage()], 500);
}
?>
