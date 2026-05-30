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
$name = isset($input['name']) ? sanitizeInput($input['name']) : 'Student';
$photo = isset($input['photo']) ? sanitizeInput($input['photo']) : '';

try {
    // 1. Check if user already exists by email OR google_id in 'users' table
    $stmt = $pdo->prepare("
        SELECT u.*, c.class_name 
        FROM users u 
        LEFT JOIN classes c ON u.class_id = c.class_id 
        WHERE (u.email = ? OR u.google_id = ?) AND u.user_type = 'student' 
        LIMIT 1
    ");
    $stmt->execute([$email, $google_id]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists - UPDATE google_id if it was missing or photo if changed
        $userId = $user['user_id'];
        
        $updateStmt = $pdo->prepare("UPDATE users SET google_id = ?, profile_picture = ? WHERE user_id = ?");
        $updateStmt->execute([$google_id, $photo, $userId]);
        
        // Return existing user data
        sendResponse('success', 'Login successful', [
            "user_id" => $user['user_id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "class_id" => $user['class_id'],
            "class_name" => $user['class_name'] ?? null,
            "board_type" => $user['board_type'] ?? null,
            "google_id" => $google_id,
            "subscription_status" => $user['subscription_status'] ?? 'inactive',
            "subscription_expiry" => $user['subscription_expiry'] ?? null,
            "is_new_user" => false
        ], 200);

    } else {
        // User is NEW - Create account now!
        // Generate a random password since it's required in some schema versions
        $random_pass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
        
        $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, google_id, profile_picture, user_type) VALUES (?, ?, ?, ?, ?, 'student')");
        $insertStmt->execute([$name, $email, $random_pass, $google_id, $photo]);
        
        $newId = $pdo->lastInsertId();
        
        // Return newly created user data
        sendResponse('success', 'Account created successfully', [
            "user_id" => $newId,
            "name" => $name,
            "email" => $email,
            "class_id" => null,
            "board_type" => null,
            "google_id" => $google_id,
            "subscription_status" => 'inactive',
            "subscription_expiry" => null,
            "is_new_user" => true
        ], 200);
    }
} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
