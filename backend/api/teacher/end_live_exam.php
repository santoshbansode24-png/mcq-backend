<?php
/**
 * End Live Exam API (Teacher)
 * Veeru
 * 
 * Endpoint: POST /api/teacher/end_live_exam.php
 * Purpose: Transition an active exam status to completed.
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

$required = ['teacher_id', 'class_id', 'exam_id'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$teacher_id = intval($input['teacher_id']);
$class_id = intval($input['class_id']);
$exam_id = intval($input['exam_id']);

try {
    $stmt = $pdo->prepare("
        UPDATE live_exams 
        SET status = 'completed' 
        WHERE id = ? AND class_id = ? AND teacher_id = ?
    ");
    $stmt->execute([$exam_id, $class_id, $teacher_id]);

    sendResponse('success', 'Live Exam ended successfully.', null, 200);

} catch (PDOException $e) {
    error_log("Error ending live exam: " . $e->getMessage());
    sendResponse('error', 'Database error occurred: ' . $e->getMessage(), null, 500);
}
?>
