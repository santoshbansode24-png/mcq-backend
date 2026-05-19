<?php
/**
 * Create Live Class API (Teacher)
 * 
 * Endpoint: POST /api/teacher/create_live_class.php
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();
$required = ['teacher_id', 'class_id', 'title', 'youtube_id', 'scheduled_time'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$teacher_id = intval($input['teacher_id']);
$class_id = intval($input['class_id']);
$title = sanitizeInput($input['title']);
$youtube_id = sanitizeInput($input['youtube_id']);
$scheduled_time = sanitizeInput($input['scheduled_time']);
$timestamp = strtotime($scheduled_time);
if ($timestamp === false) {
    sendResponse('error', 'Invalid scheduled time format. Please use YYYY-MM-DD HH:MM:SS or a standard readable phrase (e.g. today 4pm)', null, 400);
}
$formatted_scheduled_time = date('Y-m-d H:i:s', $timestamp);
$message = isset($input['message']) ? sanitizeInput($input['message']) : '';

if ($teacher_id <= 0 || $class_id <= 0) {
    sendResponse('error', 'Invalid teacher_id or class_id', null, 400);
}

if (strlen($title) < 3) {
    sendResponse('error', 'Title must be at least 3 characters long', null, 400);
}

if (strlen($youtube_id) !== 11) {
    sendResponse('error', 'Invalid YouTube Video ID. Must be exactly 11 characters.', null, 400);
}

try {
    // 1. Verify teacher exists and get school_name
    $teacherStmt = $pdo->prepare("SELECT school_name FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $teacherStmt->execute([$teacher_id]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher) {
        sendResponse('error', 'Invalid teacher ID', null, 403);
    }
    $school_name = $teacher['school_name'];
    
    // 2. Verify class exists
    $classStmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_id = ?");
    $classStmt->execute([$class_id]);
    if (!$classStmt->fetch()) {
        sendResponse('error', 'Invalid class ID', null, 400);
    }
    
    // 3. Construct payload
    $payload = json_encode([
        'youtube_id' => $youtube_id,
        'scheduled_time' => $formatted_scheduled_time
    ]);

    // 4. Insert into class_updates table
    $stmt = $pdo->prepare("
        INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload, created_at) 
        VALUES (?, ?, ?, 'live_class', ?, ?, ?, NOW())
    ");
    $stmt->execute([$teacher_id, $school_name, $class_id, $title, $message, $payload]);
    $update_id = $pdo->lastInsertId();

    sendResponse('success', 'Live class scheduled successfully', ['update_id' => $update_id], 201);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
