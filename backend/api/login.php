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

// Debug Log
$log_msg = date('Y-m-d H:i:s') . " Attempt - Email: " . ($input['email'] ?? 'MISSING') . " (JSON: " . json_encode($input) . ")\n";
file_put_contents('../login_debug.log', $log_msg, FILE_APPEND);

// Validate required fields
$required = ['email', 'password'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

// Extract and sanitize inputs
$email = sanitizeInput($input['email']);
$password = $input['password'];

// --- START BULLETPROOF REVIEWER BYPASS ---
// Check this BEFORE database lookup to ensure it works even if record is missing.
$isReviewerBypass = ($email === 'reviewer@veeru.com' && ($password === 'veeru123' || $password === 'Reviewer@2024'));

if ($isReviewerBypass) {
    file_put_contents('../login_debug.log', date('Y-m-d H:i:s') . " Reviewer Bypass triggered for: $email\n", FILE_APPEND);
    $reviewerUser = [
        'user_id' => 999, // Static ID for reviewer
        'name' => 'Reviewer Account',
        'email' => 'reviewer@veeru.com',
        'user_type' => 'student',
        'class_id' => 10, // Default to Class 3 (mapped to ID 10 in your DB)
        'class_name' => 'Class 3',
        'subscription_status' => 'active',
        'subscription_expiry' => '2099-12-31',
        'login_streak' => 1
    ];
    sendResponse('success', 'Reviewer Login successful', $reviewerUser, 200);
}
// --- END BULLETPROOF REVIEWER BYPASS ---

try {
    // Query database for user (by Email OR Mobile)
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, password, user_type, class_id, 
               subscription_status, subscription_expiry, last_login, 
               login_streak, school_name, board_type, mobile
        FROM users 
        WHERE (email = ? OR mobile = ?) AND user_type = 'student'
    ");
    
    // Pass the same input twice to check against both columns
    $stmt->execute([$email, $email]); 
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        file_put_contents('../login_debug.log', date('Y-m-d H:i:s') . " Login Fail: User not found for: $email\n", FILE_APPEND);
        sendResponse('error', 'Invalid email or password', null, 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        file_put_contents('../login_debug.log', date('Y-m-d H:i:s') . " Login Fail: Password mismatch for: $email\n", FILE_APPEND);
        sendResponse('error', 'Invalid email or password', null, 401);
    }
    
    // Check subscription status (skip for reviewer)
    if (!$isReviewerBypass && $user['subscription_status'] !== 'active') {
        sendResponse('error', 'Your subscription is inactive. Please renew to continue.', null, 403);
    }
    // --- END REVIEWER BYPASS ---
    
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
