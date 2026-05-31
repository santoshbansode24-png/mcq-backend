<?php
/**
 * Teacher Registration API (Open Registration with School Name)
 * Veeru
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();
if (!$input) {
    $input = $_POST;
}

$required = ['name', 'email', 'password', 'school_name'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields.', ['missing' => $missing], 400);
}

$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$password = $input['password'];
$school_name = sanitizeInput($input['school_name']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format', null, 400);
}
if (strlen($password) < 6) {
    sendResponse('error', 'Password must be at least 6 characters long', null, 400);
}

try {
    // Check if email already exists
    $emailStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $emailStmt->execute([$email]);
    if ($emailStmt->fetch()) {
        sendResponse('error', 'Email is already registered.', null, 409);
    }

    // Register the Teacher
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $insertStmt = $pdo->prepare("
        INSERT INTO users (name, email, password, user_type, subscription_status, school_name, created_at)
        VALUES (?, ?, ?, 'teacher', 'active', ?, NOW())
    ");
    $insertStmt->execute([$name, $email, $hashedPassword, $school_name]);
    $user_id = $pdo->lastInsertId();

    sendResponse('success', 'Teacher account created successfully!', [
        'user_id' => $user_id,
        'name' => $name,
        'email' => $email,
        'school_name' => $school_name,
        'user_type' => 'teacher'
    ], 201);

} catch (PDOException $e) {
    error_log("Teacher Registration Error: " . $e->getMessage());
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
