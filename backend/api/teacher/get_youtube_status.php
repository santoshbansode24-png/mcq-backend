<?php
/**
 * Get YouTube Connection Status (Teacher)
 * 
 * Endpoint: GET /api/teacher/get_youtube_status.php
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

if (!isset($_GET['teacher_id']) || empty($_GET['teacher_id'])) {
    sendResponse('error', 'teacher_id is required', null, 400);
}

$teacher_id = intval($_GET['teacher_id']);

try {
    $stmt = $pdo->prepare("SELECT youtube_refresh_token, youtube_channel_id FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $stmt->execute([$teacher_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse('error', 'Teacher not found', null, 404);
    }
    
    $is_connected = !empty($user['youtube_refresh_token']);
    
    sendResponse('success', 'YouTube status retrieved', [
        'connected' => $is_connected,
        'channel_id' => $user['youtube_channel_id']
    ]);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
