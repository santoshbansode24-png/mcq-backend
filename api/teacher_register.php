<?php
/**
 * Teacher Registration API (with Access Code)
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

$required = ['name', 'email', 'password', 'access_code'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields.', ['missing' => $missing], 400);
}

$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$password = $input['password'];
$access_code = strtoupper(trim(sanitizeInput($input['access_code'])));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format', null, 400);
}
if (strlen($password) < 6) {
    sendResponse('error', 'Password must be at least 6 characters long', null, 400);
}

try {
    // 1. Validate the Access Code
    $codeStmt = $pdo->prepare("SELECT school_id, school_name, valid_until, max_teachers FROM school_subscriptions WHERE access_code = ?");
    $codeStmt->execute([$access_code]);
    $school = $codeStmt->fetch();

    if (!$school) {
        sendResponse('error', 'Invalid School Access Code.', null, 403);
    }

    // Set expiry to the end of the day (23:59:59) so they don't expire prematurely on the day of expiry
    $expiry_timestamp = strtotime($school['valid_until'] . ' 23:59:59');
    if ($expiry_timestamp < time()) {
        sendResponse('error', "This school's subscription expired on " . $school['valid_until'] . ".", null, 403);
    }

    $school_id = $school['school_id'];
    $school_name = $school['school_name'];

    // Check teacher limit
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND user_type = 'teacher'");
    $countStmt->execute([$school_id]);
    $current_teachers = $countStmt->fetchColumn();

    if ($school['max_teachers'] > 0 && $current_teachers >= $school['max_teachers']) {
        sendResponse('error', 'Registration full: This school has reached its maximum number of authorized teachers.', null, 403);
    }

    // 2. Check if email already exists
    $emailStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $emailStmt->execute([$email]);
    if ($emailStmt->fetch()) {
        sendResponse('error', 'Email is already registered.', null, 409);
    }

    // 3. Register the Teacher
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $insertStmt = $pdo->prepare("
        INSERT INTO users (name, email, password, user_type, subscription_status, school_name, school_id, created_at)
        VALUES (?, ?, ?, 'teacher', 'active', ?, ?, NOW())
    ");
    $insertStmt->execute([$name, $email, $hashedPassword, $school_name, $school_id]);
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
