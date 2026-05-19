<?php
/**
 * Get Live Class Chat Messages API
 * 
 * Endpoint: GET /api/student/get_live_chat.php
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

$class_update_id = isset($_GET['class_update_id']) ? intval($_GET['class_update_id']) : 0;
$last_chat_id = isset($_GET['last_chat_id']) ? intval($_GET['last_chat_id']) : 0;

if ($class_update_id <= 0) {
    sendResponse('error', 'Class Update ID is required', null, 400);
}

try {
    // Fetch comments for this live session
    // If last_chat_id is specified, only fetch newer comments
    $stmt = $pdo->prepare("
        SELECT id, student_id, student_name, message, created_at 
        FROM live_class_chat 
        WHERE class_update_id = ? AND id > ? 
        ORDER BY id ASC 
        LIMIT 100
    ");
    $stmt->execute([$class_update_id, $last_chat_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse('success', 'Messages retrieved', $messages, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
