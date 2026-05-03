<?php
/**
 * Teacher Login API
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();

$required = ['email', 'password'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$email = sanitizeInput($input['email']);
$password = $input['password'];

try {
    $stmt = $pdo->prepare("SELECT user_id as id, user_id, name, email, password, school_name, mobile FROM users WHERE email = ? AND user_type = 'teacher'");
    $stmt->execute([$email]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        sendResponse('error', 'Invalid email or password', null, 401);
    }

    if (!password_verify($password, $teacher['password'])) {
        sendResponse('error', 'Invalid email or password', null, 401);
    }

    // Get assigned classes
    $classStmt = $pdo->prepare("SELECT class_id FROM teacher_classes WHERE teacher_id = ?");
    $classStmt->execute([$teacher['user_id']]);
    $classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);

    unset($teacher['password']);
    $teacher['classes'] = $classes;
    $teacher['user_type'] = 'teacher';

    sendResponse('success', 'Login successful', $teacher, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
