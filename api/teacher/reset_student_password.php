<?php
/**
 * Teacher Reset Student Password API
 * Veeru
 * Endpoint: POST /api/teacher/reset_student_password.php
 */
require_once __DIR__ . '/../cors_middleware.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput() ?: $_POST;

$teacher_id = isset($input['teacher_id']) ? intval($input['teacher_id']) : 0;
$student_id = isset($input['student_id']) ? intval($input['student_id']) : (isset($input['target_user_id']) ? intval($input['target_user_id']) : 0);
$new_password = isset($input['new_password']) && !empty(trim($input['new_password'])) ? trim($input['new_password']) : 'Student@123';
$new_pin = isset($input['new_pin']) ? trim($input['new_pin']) : null;

if ($student_id <= 0) {
    sendResponse('error', 'Target student_id is required', null, 400);
}

try {
    $targetStmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE user_id = ?");
    $targetStmt->execute([$student_id]);
    $student = $targetStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        sendResponse('error', 'Student not found', null, 404);
    }

    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    if (!empty($new_pin) && preg_match('/^\d{4}$/', $new_pin)) {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, security_pin = ? WHERE user_id = ?");
        $updateStmt->execute([$hashed_password, $new_pin, $student_id]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $updateStmt->execute([$hashed_password, $student_id]);
    }

    sendResponse('success', 'Password reset successfully for ' . $student['name'], [
        'student_id' => $student_id,
        'temporary_password' => $new_password,
        'security_pin' => $new_pin
    ], 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred: ' . $e->getMessage(), null, 500);
}
?>
