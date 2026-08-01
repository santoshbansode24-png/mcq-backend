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
    $is_mobile = false;
    $search_value = $email;

    if (strpos($email, '@') === false && is_numeric($cleaned_digits) && strlen($cleaned_digits) >= 10) {
        $is_mobile = true;
        // Extract the last 10 digits as the core mobile number
        $search_value = substr($cleaned_digits, -10);
        $field_query = "(RIGHT(mobile, 10) = ? OR RIGHT(phone, 10) = ? OR RIGHT(phone_number, 10) = ?)";
    } else {
        $field_query = "LOWER(email) = LOWER(?)";
    }

    // Query database for user
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, password, user_type, class_id, 
               subscription_status, subscription_expiry 
        FROM users 
        WHERE $field_query AND user_type IN ('student', 'teacher', 'admin')
    ");
    
    if ($is_mobile) {
        $stmt->execute([$search_value, $search_value, $search_value]); 
    } else {
        $stmt->execute([$search_value]); 
    }
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        file_put_contents('../login_debug.log', date('Y-m-d H:i:s') . " Login Fail (Root API): No user found for: $email (IsMobile: " . ($is_mobile ? 'Yes' : 'No') . ")\n", FILE_APPEND);
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        file_put_contents('../login_debug.log', date('Y-m-d H:i:s') . " Login Fail (Root API): Password mismatch for user ID: " . $user['user_id'] . " (Email: " . $user['email'] . ")\n", FILE_APPEND);
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
