<?php
/**
 * Send Live Class Chat Message API
 * 
 * Endpoint: POST /api/student/send_live_chat.php
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();
$required = ['student_id', 'class_update_id', 'message'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$student_id = intval($input['student_id']);
$class_update_id = intval($input['class_update_id']);
$message = sanitizeInput($input['message']);

if (empty($message)) {
    sendResponse('error', 'Message cannot be empty', null, 400);
}

try {
    // 1. Fetch student's name
    $userStmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
    $userStmt->execute([$student_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse('error', 'Student not found', null, 404);
    }
    $student_name = $user['name'];

    // 2. Insert chat message
    $insertStmt = $pdo->prepare("
        INSERT INTO live_class_chat (class_update_id, student_id, student_name, message) 
        VALUES (?, ?, ?, ?)
    ");
    $insertStmt->execute([$class_update_id, $student_id, $student_name, $message]);
    $chat_id = $pdo->lastInsertId();

    $newChat = [
        'id' => $chat_id,
        'student_id' => $student_id,
        'student_name' => $student_name,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s')
    ];

    sendResponse('success', 'Message sent', $newChat, 201);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
