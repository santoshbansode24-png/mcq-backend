<?php
/**
 * Update Student Class API
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

// Validate required fields
$required = ['user_id', 'class_id'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

// Sanitize inputs
$user_id = sanitizeInput($input['user_id']);
$class_id = sanitizeInput($input['class_id']);
$board_type = isset($input['board_type']) ? sanitizeInput($input['board_type']) : 'STATE_MARATHI';

try {
    // Check if class exists
    $classStmt = $pdo->prepare("SELECT class_name FROM classes WHERE class_id = ?");
    $classStmt->execute([$class_id]);
    $class = $classStmt->fetch();

    if (!$class) {
        sendResponse('error', 'Invalid class ID', null, 400);
    }

// Update student class and board
    $stmt = $pdo->prepare("UPDATE users SET class_id = ?, board_type = ? WHERE user_id = ?");
    $stmt->execute([$class_id, $board_type, $user_id]);
    
    // Fetch new class name for response
    $stmt = $pdo->prepare("SELECT class_name FROM classes WHERE class_id = ?");
    $stmt->execute([$class_id]);
    $class_name = $stmt->fetchColumn();
    
    // Clear any cached data for this user if needed
    
    sendResponse('success', 'Class updated successfully', ['class_name' => $class_name, 'board_type' => $board_type], 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
