<?php
/**
 * Get Teacher Classes API
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

$teacher_id = isset($_GET['teacher_id']) ? filter_var($_GET['teacher_id'], FILTER_VALIDATE_INT) : 0;

if ($teacher_id <= 0) {
    sendResponse('error', 'Valid teacher_id is required', null, 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT tc.class_id, c.class_name, 
               (SELECT COUNT(*) FROM users u WHERE u.class_id = tc.class_id AND u.school_name = t.school_name AND u.user_type = 'student') as student_count
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.class_id
        JOIN users t ON tc.teacher_id = t.user_id AND t.user_type = 'teacher'
        WHERE tc.teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse('success', 'Classes fetched successfully', $classes, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
