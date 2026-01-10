<?php
/**
 * Student Registration API
 * Veeru
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Temporary Debug Logging
file_put_contents('../debug_register.log', print_r(getJsonInput(), true), FILE_APPEND);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

// Validate required fields
$required = ['name', 'email', 'mobile', 'password', 'school_name', 'class_id', 'board'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

// Sanitize inputs
$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$mobile = sanitizeInput($input['mobile']);
$password = $input['password'];
$school_name = sanitizeInput($input['school_name']);
$class_id = filter_var($input['class_id'], FILTER_VALIDATE_INT);
$board = sanitizeInput($input['board']);

// Validate Board
if (!in_array($board, ['CBSE', 'State Board'])) {
    sendResponse('error', 'Invalid board selection', null, 400);
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format', null, 400);
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendResponse('error', 'Email already registered', null, 409);
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Default values
    $user_type = 'student';
    $subscription_status = 'active'; // Default to active for now
    $subscription_expiry = date('Y-m-d', strtotime('+30 days')); // 30 days trial/active
    
    // Insert new user
    $insertStmt = $pdo->prepare("
        INSERT INTO users (name, email, mobile, password, user_type, subscription_status, subscription_expiry, school_name, class_id, board, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $insertStmt->execute([
        $name, 
        $email,
        $mobile,
        $hashed_password, 
        $user_type, 
        $subscription_status, 
        $subscription_expiry,
        $school_name,
        $class_id,
        $board
    ]);

    $user_id = $pdo->lastInsertId();

    // Fetch the newly created user to return (excluding password)
    $userStmt = $pdo->prepare("SELECT user_id, name, email, user_type, subscription_status, class_id FROM users WHERE user_id = ?");
    $userStmt->execute([$user_id]);
    $newUser = $userStmt->fetch();

    sendResponse('success', 'Registration successful', $newUser, 201);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
