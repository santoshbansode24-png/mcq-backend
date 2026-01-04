<?php
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

// Validate required fields
$required = ['user_id', 'push_token'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$user_id = $input['user_id'];
$push_token = $input['push_token'];

try {
    // Update user's push token
    $stmt = $pdo->prepare("UPDATE users SET push_token = ? WHERE user_id = ?");
    $stmt->execute([$push_token, $user_id]);
    
    sendResponse('success', 'Push token registered successfully', null, 200);
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
