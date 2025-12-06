<?php
/**
 * Get Notifications API (Teacher)
 * MCQ Project 2.0
 * 
 * Endpoint: GET /api/teacher/get_notifications.php?teacher_id=1
 * Purpose: Get all notifications sent by a teacher
 */

require_once '../../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get teacher_id from query parameter
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

// Validate teacher_id
if ($teacher_id <= 0) {
    sendResponse('error', 'Valid teacher_id is required', null, 400);
}

try {
    // Query notifications sent by teacher
    $stmt = $pdo->prepare("
        SELECT 
            n.notification_id,
            n.teacher_id,
            n.class_id,
            n.title,
            n.message,
            n.created_at,
            c.class_name,
            (SELECT COUNT(*) FROM users WHERE class_id = n.class_id AND user_type = 'student') as students_count
        FROM notifications n
        INNER JOIN classes c ON n.class_id = c.class_id
        WHERE n.teacher_id = ?
        ORDER BY n.created_at DESC
    ");
    
    $stmt->execute([$teacher_id]);
    $notifications = $stmt->fetchAll();
    
    // Check if notifications exist
    if (empty($notifications)) {
        sendResponse('success', 'No notifications found', [], 200);
    }
    
    // Success response
    sendResponse('success', 'Notifications retrieved successfully', $notifications, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
