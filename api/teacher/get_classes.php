<?php
/**
 * Get Teacher's Classes API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/get_classes.php
 * Purpose: Get all classes assigned to a teacher
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

// Validate required fields
$required = ['teacher_id'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$teacher_id = (int)$input['teacher_id'];

try {
    // Get assigned classes with student count for this specific teacher
    $stmt = $pdo->prepare("
        SELECT 
            c.class_id,
            c.class_name,
            tc.division_name,
            tc.class_code,
            (SELECT COUNT(*) FROM users u WHERE u.class_id = c.class_id AND u.user_type = 'student') as student_count
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.class_id
        WHERE tc.teacher_id = ?
        ORDER BY c.class_name ASC
    ");
    
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll();
    
    sendResponse('success', 'Classes fetched successfully', $classes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
