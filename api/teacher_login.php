<?php
/**
 * Teacher Login API
 * Veeru
 * 
 * Endpoint: POST /api/teacher_login.php
 * Purpose: Authenticate teacher users
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed. Received: ' . $_SERVER['REQUEST_METHOD'], null, 405);
}

// Get JSON input or standard POST data (Hybrid Support)
$input = getJsonInput();
if (!$input) {
    $input = $_POST;
}

// Validate required fields
$required = ['email', 'password'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Please enter email and password.', ['missing' => $missing], 400);
}

// Sanitize inputs
$email = sanitizeInput($input['email']);
$password = $input['password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format', null, 400);
}

try {
    // Robust query: case-insensitive user_type check and trimmed email
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, password, user_type, phone, school_name, mobile
        FROM users 
        WHERE LOWER(email) = LOWER(?) AND LOWER(user_type) = 'teacher'
    ");
    
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        error_log("Teacher login failed: No user found for $email");
        sendResponse('error', 'Invalid email or password', null, 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        error_log("Teacher login failed: Password mismatch for $email");
        sendResponse('error', 'Invalid email or password', null, 401);
    }
    
    // Check School Subscription Status
    $schoolStmt = $pdo->prepare("
        SELECT u.school_id, s.valid_until, s.school_name 
        FROM users u
        LEFT JOIN school_subscriptions s ON u.school_id = s.school_id 
        WHERE u.user_id = ?
    ");
    $schoolStmt->execute([$user['user_id']]);
    $schoolSub = $schoolStmt->fetch();

    if (!$schoolSub['school_id'] || empty($schoolSub['valid_until'])) {
        sendResponse('error', "Your account is not linked to an active school subscription.", null, 403);
    } else {
        $expiry_timestamp = strtotime($schoolSub['valid_until'] . ' 23:59:59');
        if ($expiry_timestamp < time()) {
            sendResponse('error', "Your school's subscription (" . $schoolSub['school_name'] . ") expired on " . $schoolSub['valid_until'] . ".", null, 403);
        }
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
