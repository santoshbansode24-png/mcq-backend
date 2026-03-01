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
    
    if (empty($input['user_id']) || empty($input['new_password']) || empty($input['reset_token'])) {
        sendResponse('error', 'User ID, reset token, and new password are required', null, 400);
    }
    
    $userId      = intval($input['user_id']);
    $newPassword = trim($input['new_password']);
    $resetToken  = trim($input['reset_token']);
    
    // Validate password strength
    if (strlen($newPassword) < 6) {
        sendResponse('error', 'Password must be at least 6 characters long', null, 400);
    }
    
    // Validate reset_token: must exist in DB, belong to this user, not expired, and have been OTP-verified
    $stmt = $pdo->prepare("
        SELECT id 
        FROM password_reset_otps 
        WHERE user_id = ? 
        AND reset_token = ?
        AND verified = TRUE 
        AND token_expires_at > NOW()
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId, $resetToken]);
    $otpRecord = $stmt->fetch();
    
    if (!$otpRecord) {
        sendResponse('error', 'Invalid or expired reset token. Please request a new OTP.', null, 403);
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
