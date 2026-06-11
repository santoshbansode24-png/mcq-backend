<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests allowed', null, 405);
}

$input = getJsonInput();
$message_id = isset($input['message_id']) ? intval($input['message_id']) : 0;
$teacher_id = isset($input['teacher_id']) ? intval($input['teacher_id']) : 0;

if ($message_id <= 0 || $teacher_id <= 0) {
    sendResponse('error', 'Message ID and Teacher ID are required.', null, 400);
}

try {
    // Check if table exists first
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'class_chat'");
    if ($tableCheck->rowCount() == 0) {
        sendResponse('error', 'Chat system not fully initialized.', null, 404);
    }
    // Verify the message exists
    $checkStmt = $pdo->prepare("
        SELECT id, sender_id, sender_role 
        FROM class_chat 
        WHERE id = ?
    ");
    $checkStmt->execute([$message_id]);
    $message = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        sendResponse('error', 'Message not found.', null, 404);
    }

    // Allow deletion if the teacher is the sender
    if ($message['sender_id'] != $teacher_id || $message['sender_role'] !== 'teacher') {
        sendResponse('error', 'Unauthorized to delete this message.', null, 403);
    }

    // Delete the message
    $delStmt = $pdo->prepare("DELETE FROM class_chat WHERE id = ?");
    $delStmt->execute([$message_id]);

    sendResponse('success', 'Message deleted successfully.', null, 200);

} catch (PDOException $e) {
    error_log("Delete Message Error: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
