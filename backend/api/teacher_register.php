<?php
/**
 * Teacher Registration API
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();

$required = ['name', 'email', 'password', 'school_name'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$password = $input['password'];
$school_name = sanitizeInput($input['school_name']);
$class_ids = isset($input['class_ids']) && is_array($input['class_ids']) ? $input['class_ids'] : []; // Array of class IDs

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format', null, 400);
}

try {
    $pdo->beginTransaction();

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id, user_type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();
    if ($existingUser) {
        $pdo->rollBack();
        if ($existingUser['user_type'] === 'teacher') {
            sendResponse('error', 'Email already registered. Please login or use a different email.', null, 409);
        } else {
            sendResponse('error', 'This email is already registered as a ' . $existingUser['user_type'] . ' account.', null, 409);
        }
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table as a teacher
    $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, school_name, user_type, subscription_status, created_at) VALUES (?, ?, ?, ?, 'teacher', 'active', NOW())");
    $insertStmt->execute([$name, $email, $hashed_password, $school_name]);
    $teacher_id = $pdo->lastInsertId();

    // Insert Classes into teacher_classes (using user_id)
    $classStmt = $pdo->prepare("INSERT IGNORE INTO teacher_classes (teacher_id, class_id) VALUES (?, ?)");
    foreach ($class_ids as $class_id) {
        $classStmt->execute([$teacher_id, filter_var($class_id, FILTER_VALIDATE_INT)]);
    }

    $pdo->commit();

    sendResponse('success', 'Teacher registration successful. You can now login.', ['teacher_id' => $teacher_id, 'name' => $name, 'school_name' => $school_name], 201);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
