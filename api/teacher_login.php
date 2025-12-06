<?php
/**
 * Teacher Login API
 * MCQ Project 2.0
 * 
 * Endpoint: POST /api/teacher_login.php
 * Purpose: Authenticate teacher users
 */

require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

// Validate required fields
$required = ['email', 'password'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

// Sanitize inputs
$email = sanitizeInput($input['email']);
$password = $input['password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format', null, 400);
}

try {
    // Query database for teacher
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, password, user_type, phone 
        FROM users 
        WHERE email = ? AND user_type = 'teacher'
    ");
    
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        sendResponse('error', 'Invalid email or password', null, 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        sendResponse('error', 'Invalid email or password', null, 401);
    }
    
    // Remove password from response
    unset($user['password']);
    
    // Get teacher statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(DISTINCT class_id) FROM notifications WHERE teacher_id = ?) as total_classes,
            (SELECT COUNT(*) FROM notifications WHERE teacher_id = ?) as notifications_sent
    ");
    $statsStmt->execute([$user['user_id'], $user['user_id']]);
    $stats = $statsStmt->fetch();
    
    $user['stats'] = $stats;
    
    // Success response
    sendResponse('success', 'Login successful', $user, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
