<?php
/**
 * Send Notification API (Teacher)
 * Veeru
 * 
 * Endpoint: POST /api/teacher/send_notification.php
 * Purpose: Allow teachers to send notifications to students
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';
require_once '../../config/push_notifications.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

// Validate required fields
$required = ['teacher_id', 'class_id', 'title', 'message'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

// Sanitize inputs
$teacher_id = intval($input['teacher_id']);
$class_id = intval($input['class_id']);
$title = sanitizeInput($input['title']);
$message = sanitizeInput($input['message']);

// Validate values
if ($teacher_id <= 0 || $class_id <= 0) {
    sendResponse('error', 'Invalid teacher_id or class_id', null, 400);
}

if (strlen($title) < 3 || strlen($title) > 200) {
    sendResponse('error', 'Title must be between 3 and 200 characters', null, 400);
}

if (strlen($message) < 10) {
    sendResponse('error', 'Message must be at least 10 characters long', null, 400);
}

try {
    // Verify teacher exists
    $teacherStmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $teacherStmt->execute([$teacher_id]);
    if (!$teacherStmt->fetch()) {
        sendResponse('error', 'Invalid teacher ID', null, 403);
    }
    
    // Verify class exists
    $classStmt = $pdo->prepare("SELECT class_id FROM classrooms WHERE class_id = ?");
    $classStmt->execute([$class_id]);
    if (!$classStmt->fetch()) {
        sendResponse('error', 'Invalid class ID', null, 400);
    }
    
    // Insert notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (teacher_id, class_id, title, message, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$teacher_id, $class_id, $title, $message]);
    
    // Get the inserted notification_id
    $notification_id = $pdo->lastInsertId();

    // Trigger instant push notifications to all students in the class
    sendClassPushNotifications($pdo, $class_id, "New Announcement: " . $title, $message, [
        'type' => 'announcement',
        'notification_id' => $notification_id,
        'screen' => 'ClassUpdates'
    ]);
    
    // Get count of students in this class
    $student_count = 0;
    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM student_class_mapping WHERE class_id = ?");
        $countStmt->execute([$class_id]);
        $count = $countStmt->fetch();
        $student_count = $count['student_count'] ?? 0;
    } catch (PDOException $e) {
        // Table might not exist, ignore and leave count at 0
    }
    
    // Prepare response
    $responseData = [
        'notification_id' => $notification_id,
        'teacher_id' => $teacher_id,
        'class_id' => $class_id,
        'title' => $title,
        'students_notified' => $student_count
    ];
    
    // Success response
    sendResponse('success', 'Notification sent successfully', $responseData, 201);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred: ' . $e->getMessage(), ['error' => $e->getMessage()], 500);
}
?>
