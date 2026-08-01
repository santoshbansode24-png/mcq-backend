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
// Validate required fields
$required = ['name', 'email', 'mobile', 'password', 'school_name', 'class_id'];
$missing = validateRequired($input, $required);

// Check board (accept board_type or board)
if (isset($input['board']) && !isset($input['board_type'])) {
    $input['board_type'] = $input['board'];
}

if (!isset($input['board_type']) || empty($input['board_type'])) {
    $missing[] = 'board_type';
}

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
$board_type = sanitizeInput($input['board_type']);

// Validate Board
if (!in_array($board_type, ['CBSE', 'STATE_MARATHI', 'STATE_SEMI'])) {
    sendResponse('error', 'Invalid board selection', null, 400);
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email format', null, 400);
}

// Clean mobile number (extract last 10 digits if formatted with country code)
$cleaned_mobile = preg_replace('/[^0-9]/', '', $mobile);
if (strlen($cleaned_mobile) >= 10) {
    $mobile = substr($cleaned_mobile, -10);
}

// Validate mobile (exactly 10 digits)
if (strlen($mobile) !== 10 || !is_numeric($mobile)) {
    sendResponse('error', 'Mobile number must be exactly 10 digits', null, 400);
}

try {
    // Check if email already registered as student
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE LOWER(email) = LOWER(?) AND user_type = 'student'");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendResponse('error', 'This email is already registered as a student account. Please login.', null, 409);
    }

    // Check if mobile already registered as student (using right-most 10-digit match)
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE RIGHT(mobile, 10) = ? AND user_type = 'student'");
    $stmt->execute([$mobile]);
    if ($stmt->fetch()) {
        sendResponse('error', 'Mobile number already registered. Please use a different number.', null, 409);
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Default values
    $user_type = 'student';
    $subscription_status = 'active'; // Default to active for now
    $subscription_expiry = date('Y-m-d', strtotime('+30 days')); // 30 days trial/active
    
    // Optional security_pin (Default to last 4 digits of mobile if not provided)
    $security_pin = isset($input['security_pin']) ? trim($input['security_pin']) : null;
    if (!empty($security_pin) && !preg_match('/^\d{4}$/', $security_pin)) {
        sendResponse('error', 'Security PIN must be exactly 4 digits', null, 400);
    }
    if (empty($security_pin) && strlen($mobile) >= 4) {
        $security_pin = substr($mobile, -4);
    }

    // Insert new user
    $insertStmt = $pdo->prepare("
        INSERT INTO users (name, email, mobile, password, security_pin, user_type, subscription_status, subscription_expiry, school_name, class_id, board_type, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $insertStmt->execute([
        $name, 
        $email,
        $mobile,
        $hashed_password, 
        $security_pin,
        $user_type, 
        $subscription_status, 
        $subscription_expiry,
        $school_name,
        $class_id,
        $board_type
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
