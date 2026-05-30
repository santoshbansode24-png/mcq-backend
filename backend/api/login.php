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
        'board_type' => 'CBSE', // Prevent redirect to Setup screen
        'school_name' => 'Veeru Reviewer',
        'mobile' => '9999999999',
        'subscription_status' => 'active',
        'subscription_expiry' => '2099-12-31',
        'login_streak' => 1
    ];
    sendResponse('success', 'Reviewer Login successful', $reviewerUser, 200);
}
// --- END BULLETPROOF REVIEWER BYPASS ---

try {
    // Optimization: determine if input is email or mobile to prevent full table scan
    $is_mobile = is_numeric($email) && strlen($email) == 10;
    $field = $is_mobile ? 'mobile' : 'email';

    // Query database for user with JOIN to get class_name in one go
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.email, u.password, u.user_type, u.class_id, 
               u.subscription_status, u.subscription_expiry, u.last_login, 
               u.login_streak, u.school_name, u.board_type, u.mobile,
               c.class_name
        FROM users u
        LEFT JOIN classes c ON u.class_id = c.class_id
        WHERE u.$field = ? AND u.user_type = 'student'
    ");
    
    $stmt->execute([$email]); 
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        file_put_contents('../login_debug.log', date('Y-m-d H:i:s') . " Login Fail: Password mismatch for: $email\n", FILE_APPEND);
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }
    
    // Removed hard block for inactive subscriptions. 
    // The frontend should handle premium feature gating, allowing the user to access the Subscription screen to renew.
    
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
    
    // Success response
    sendResponse('success', 'Login successful', $user, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
