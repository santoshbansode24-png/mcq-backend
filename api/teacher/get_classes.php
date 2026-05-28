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
            cr.class_id,
            cr.class_name,
            tc.division_name,
            tc.class_code,
            (SELECT COUNT(*) FROM student_class_mapping scm WHERE scm.class_id = cr.class_id) as student_count
        FROM teacher_classes tc
        JOIN classrooms cr ON tc.class_code = cr.class_code
        WHERE tc.teacher_id = ?
        ORDER BY cr.class_name ASC
    ");
    
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll();
    
    sendResponse('success', 'Classes fetched successfully', $classes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
