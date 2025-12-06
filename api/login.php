<?php
/**
 * Student Login API
 * MCQ Project 2.0
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
    // Query database for user
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, password, user_type, class_id, 
               subscription_status, subscription_expiry 
        FROM users 
        WHERE email = ? AND user_type = 'student'
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
