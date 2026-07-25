<?php
/**
 * Admin / Teacher Reset Student Password API
 * Veeru
 * 
 * Endpoint: POST /backend/api/admin_reset_user_password.php
 */

require_once '../../api/cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput() ?: $_POST;

$requester_id = isset($input['requester_id']) ? intval($input['requester_id']) : (isset($input['teacher_id']) ? intval($input['teacher_id']) : 0);
$target_user_id = isset($input['target_user_id']) ? intval($input['target_user_id']) : (isset($input['student_id']) ? intval($input['student_id']) : 0);
$new_password = isset($input['new_password']) && !empty(trim($input['new_password'])) ? trim($input['new_password']) : 'Student@123';
$new_pin = isset($input['new_pin']) ? trim($input['new_pin']) : null;

if ($target_user_id <= 0) {
    sendResponse('error', 'Target user_id is required', null, 400);
}

try {
    // 1. Verify target user exists
    $targetStmt = $pdo->prepare("SELECT user_id, name, email, user_type FROM users WHERE user_id = ?");
    $targetStmt->execute([$target_user_id]);
    $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        sendResponse('error', 'Target user not found', null, 404);
    }

    // 2. Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    if (!empty($new_pin) && preg_match('/^\d{4}$/', $new_pin)) {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, security_pin = ? WHERE user_id = ?");
        $updateStmt->execute([$hashed_password, $new_pin, $target_user_id]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $updateStmt->execute([$hashed_password, $target_user_id]);
    }

    sendResponse('success', 'Password reset successfully for ' . $targetUser['name'], [
        'target_user_id' => $target_user_id,
        'temporary_password' => $new_password,
        'security_pin' => $new_pin
    ], 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred: ' . $e->getMessage(), null, 500);
}
?>
