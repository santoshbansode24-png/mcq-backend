<?php
/**
 * Student Login API
 * Veeru
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed. Received: ' . $_SERVER['REQUEST_METHOD'], null, 405);
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

// Validate email format (Skip strict email check if it looks like a phone number)
// Simple check: If it has no '@', assume it's a mobile number, otherwise validate email
if (strpos($email, '@') !== false) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse('error', 'Invalid email format', null, 400);
    }
}

try {
    // Clean input to check if it's a mobile number (handles +91, spaces, leading zero)
    $cleaned_digits = preg_replace('/[^0-9]/', '', $email);
    $mobile_search = $email;
    if (strpos($email, '@') === false && is_numeric($cleaned_digits) && strlen($cleaned_digits) >= 10) {
        $mobile_search = substr($cleaned_digits, -10);
    }

    // Query database for user (by Email OR Mobile with right-most 10-digit match)
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, password, user_type, class_id, 
               subscription_status, subscription_expiry 
        FROM users 
        WHERE (email = ? OR RIGHT(mobile, 10) = ?) AND user_type = 'student'
    ");
    
    // Pass the input email and cleaned mobile number
    $stmt->execute([$email, $mobile_search]); 
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }
    
    // Check subscription status
    if ($user['subscription_status'] !== 'active') {
        sendResponse('error', 'Your subscription is inactive. Please renew to continue.', null, 403);
    }
    
    // Update Login Streak and Last Login
    $today = date('Y-m-d');
    $last_login_db = $user['last_login'] ?? null;
    $last_login_date = $last_login_db ? date('Y-m-d', strtotime($last_login_db)) : null;

    if ($last_login_date !== $today) {
        $streak = $user['login_streak'] ?? 0;
        
        if ($last_login_date === date('Y-m-d', strtotime('-1 day'))) {
            // Consecutive day, increment streak
            $streak++;
        } else {
            // Missed a day or first login, reset streak
            $streak = 1;
        }

        // Update DB
        $updateStmt = $pdo->prepare("UPDATE users SET login_streak = ?, last_login = NOW() WHERE user_id = ?");
        $updateStmt->execute([$streak, $user['user_id']]);
        
        // Update user array to return new values
        $user['login_streak'] = $streak;
    }

    // Remove password from response
    unset($user['password']);
    
    // Get class name
    if ($user['class_id']) {
        $classStmt = $pdo->prepare("SELECT class_name FROM classes WHERE class_id = ?");
        $classStmt->execute([$user['class_id']]);
        $class = $classStmt->fetch();
        $user['class_name'] = $class ? $class['class_name'] : null;
    }
    
    // Success response
    sendResponse('success', 'Login successful', $user, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
