<?php
/**
 * Google Login/Signup API
 * Veeru
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

if (!$input || !isset($input['email'])) {
    sendResponse('error', 'Invalid input data', null, 400);
}

// Sanitize inputs
$email = sanitizeInput($input['email']);
$google_id = isset($input['id']) ? sanitizeInput($input['id']) : ''; // Google UID

try {
    // 1. Check if user already exists by email OR google_id in 'users' table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR google_id = ?) AND user_type = 'student' LIMIT 1");
    $stmt->execute([$email, $google_id]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists - UPDATE google_id if it was missing or photo if changed
        $userId = $user['user_id'];
        $photo = isset($input['photo']) ? sanitizeInput($input['photo']) : ($user['profile_picture'] ?? '');
        
        $updateStmt = $pdo->prepare("UPDATE users SET google_id = ?, profile_picture = ? WHERE user_id = ?");
        $updateStmt->execute([$google_id, $photo, $userId]);
        
        // Return existing user data
        sendResponse('success', 'Login successful', [
            "user_id" => $user['user_id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "class_id" => $user['class_id'],
            "board_type" => $user['board_type'] ?? null,
            "google_id" => $google_id
        ], 200);

    } else {
        // User is NEW - DO NOT create account yet. 
        // Return a special status so the frontend can redirect to the manual Registration screen
        sendResponse('new_user', 'No account found. Please register.', [
            "name" => $input['name'] ?? '',
            "email" => $email,
            "google_id" => $google_id,
            "photo" => $input['photo'] ?? ''
        ], 200);
    }
} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
