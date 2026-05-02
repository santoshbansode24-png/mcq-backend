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

$required = ['name', 'email', 'password', 'school_name', 'class_ids'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$password = $input['password'];
$school_name = sanitizeInput($input['school_name']);
$class_ids = $input['class_ids']; // Array of class IDs

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format', null, 400);
}

if (!is_array($class_ids) || empty($class_ids)) {
    sendResponse('error', 'You must select at least one class', null, 400);
}

try {
    $pdo->beginTransaction();

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        sendResponse('error', 'Email already registered as a teacher.', null, 409);
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert Teacher
    $insertStmt = $pdo->prepare("INSERT INTO teachers (name, email, password_hash, school_name) VALUES (?, ?, ?, ?)");
    $insertStmt->execute([$name, $email, $hashed_password, $school_name]);
    $teacher_id = $pdo->lastInsertId();

    // Insert Classes
    $classStmt = $pdo->prepare("INSERT IGNORE INTO teacher_classes (teacher_id, class_id) VALUES (?, ?)");
    foreach ($class_ids as $class_id) {
        $classStmt->execute([$teacher_id, filter_var($class_id, FILTER_VALIDATE_INT)]);
    }

    $pdo->commit();

    sendResponse('success', 'Teacher registration successful', ['teacher_id' => $teacher_id, 'name' => $name, 'school_name' => $school_name], 201);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
