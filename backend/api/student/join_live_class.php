<?php
/**
 * Student Join Live Class / Attendance API
 * 
 * Endpoint: POST /api/student/join_live_class.php
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();
$required = ['student_id', 'class_update_id'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$student_id = intval($input['student_id']);
$class_update_id = intval($input['class_update_id']);

try {
    // 1. Verify class update exists and is of type live_class
    $updateStmt = $pdo->prepare("SELECT update_id FROM class_updates WHERE update_id = ? AND update_type = 'live_class'");
    $updateStmt->execute([$class_update_id]);
    if (!$updateStmt->fetch()) {
        sendResponse('error', 'Invalid live class session', null, 404);
    }

    // 2. Check if attendance already logged for this student in this session
    $checkStmt = $pdo->prepare("SELECT id FROM live_class_attendance WHERE class_update_id = ? AND student_id = ?");
    $checkStmt->execute([$class_update_id, $student_id]);
    
    if ($checkStmt->fetch()) {
        // Attendance already recorded, just return success
        sendResponse('success', 'Attendance already recorded', null, 200);
    }

    // 3. Log attendance
    $insertStmt = $pdo->prepare("INSERT INTO live_class_attendance (class_update_id, student_id) VALUES (?, ?)");
    $insertStmt->execute([$class_update_id, $student_id]);

    sendResponse('success', 'Attendance logged successfully', null, 201);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
