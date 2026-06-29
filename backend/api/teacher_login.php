<?php
/**
 * Teacher Login API
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input or standard POST data (Hybrid Support)
$input = getJsonInput();
if (!$input) {
    $input = $_POST;
}

$required = ['email', 'password'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Please enter your email and password.', ['missing' => $missing], 400);
}

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
    }

    // Query user by email or mobile first to provide better feedback
    $stmt = $pdo->prepare("SELECT user_id as id, user_id, name, email, password, school_name, mobile, user_type FROM users WHERE $field_query");
    if ($is_mobile) {
        $stmt->execute([$search_value, $search_value]);
    } else {
        $stmt->execute([$search_value]);
    }
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        error_log("Teacher login failed: No user found for $email");
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }

    // Verify user type
    if (strtolower($teacher['user_type']) !== 'teacher') {
        error_log("Teacher login failed: Account $email is registered as a " . $teacher['user_type']);
        sendResponse('error', 'This account is registered as a ' . $teacher['user_type'] . '. Please use a teacher account.', null, 401);
    }

    if (!password_verify($password, $teacher['password'])) {
        error_log("Teacher login failed: Password mismatch for $email");
        sendResponse('error', 'Invalid email/mobile or password', null, 401);
    }

    // Get assigned classes
    $classStmt = $pdo->prepare("SELECT class_id FROM teacher_classes WHERE teacher_id = ?");
    $classStmt->execute([$teacher['user_id']]);
    $classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);

    unset($teacher['password']);
    $teacher['classes'] = $classes;
    $teacher['user_type'] = 'teacher';

    sendResponse('success', 'Login successful', $teacher, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
