<?php
/**
 * Send Notification API (Teacher)
 * MCQ Project 2.0
 * 
 * Endpoint: POST /api/teacher/send_notification.php
 * Purpose: Allow teachers to send notifications to students
 */

require_once '../../config/db.php';

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
    $classStmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_id = ?");
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
    
    // Get count of students in this class
    $countStmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM users WHERE class_id = ? AND user_type = 'student'");
    $countStmt->execute([$class_id]);
    $count = $countStmt->fetch();
    
    // Prepare response
    $responseData = [
        'notification_id' => $notification_id,
        'teacher_id' => $teacher_id,
        'class_id' => $class_id,
        'title' => $title,
        'students_notified' => $count['student_count']
    ];
    
    // Success response
    sendResponse('success', 'Notification sent successfully', $responseData, 201);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
