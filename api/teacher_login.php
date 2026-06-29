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

try {
    // Clean input to check if it's a mobile number
    $cleaned_digits = preg_replace('/[^0-9]/', '', $email);
    $is_mobile = false;
    $search_value = $email;

    if (strpos($email, '@') === false && is_numeric($cleaned_digits) && strlen($cleaned_digits) >= 10) {
        $is_mobile = true;
        $search_value = substr($cleaned_digits, -10);
        $field_query = "(RIGHT(mobile, 10) = ? OR RIGHT(phone, 10) = ?)";
    } else {
        $field_query = "LOWER(email) = LOWER(?)";
        // Validate email format if not a mobile number
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse('error', 'Invalid email format', null, 400);
        }
    }

    // Query user by email or mobile first to provide better feedback
    $stmt = $pdo->prepare("
        SELECT user_id as id, user_id, name, email, password, user_type, phone, school_name, mobile
        FROM users 
        WHERE $field_query
    ");
    
    if ($is_mobile) {
        $stmt->execute([$search_value, $search_value]);
    } else {
        $stmt->execute([$search_value]);
    }
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        error_log("Teacher login failed: No user found for $email");
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }

    // Verify user type
    if (strtolower($user['user_type']) !== 'teacher') {
        error_log("Teacher login failed: Account $email is registered as a " . $user['user_type']);
        sendResponse('error', 'This account is registered as a ' . $user['user_type'] . '. Please use a teacher account.', null, 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        error_log("Teacher login failed: Password mismatch for $email");
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }
    
    // School name is retrieved directly from the users table.
    // Teacher app is free, so we no longer block them based on school_subscriptions.
    
    // Get assigned classes to prevent app from falsely redirecting to ClassroomSetup
    $classStmt = $pdo->prepare("SELECT class_id FROM teacher_classes WHERE teacher_id = ?");
    $classStmt->execute([$user['user_id']]);
    $classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);
    
    unset($user['password']);
    $user['classes'] = $classes;
    
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
