<?php
/**
 * Join Class by Code API (Student)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();

$required = ['user_id', 'class_code'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$user_id = filter_var($input['user_id'], FILTER_VALIDATE_INT);
$class_code = strtoupper(sanitizeInput($input['class_code']));

try {
    // 1. Find the class and teacher based on the code
    $stmt = $pdo->prepare("
        SELECT tc.teacher_id, tc.class_id, u.school_name, c.class_name
        FROM teacher_classes tc
        JOIN users u ON tc.teacher_id = u.user_id AND u.user_type = 'teacher'
        JOIN classes c ON tc.class_id = c.class_id
        WHERE tc.class_code = ?
    ");
    $stmt->execute([$class_code]);
    $classInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$classInfo) {
        sendResponse('error', 'Invalid Class Code. Please check and try again.', null, 404);
    }

    // 2. Update the student's record
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET school_name = ?, class_id = ?
        WHERE user_id = ? AND user_type = 'student'
    ");
    $updated = $updateStmt->execute([$classInfo['school_name'], $classInfo['class_id'], $user_id]);

    if ($updateStmt->rowCount() > 0 || $updated) {
        sendResponse('success', 'Successfully joined Class ' . $classInfo['class_name'] . ' at ' . $classInfo['school_name'], [
            'school_name' => $classInfo['school_name'],
            'class_id' => $classInfo['class_id'],
            'class_name' => $classInfo['class_name']
        ], 200);
    } else {
        sendResponse('error', 'Failed to update student profile. Ensure you are logged in as a student.', null, 400);
    }

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
