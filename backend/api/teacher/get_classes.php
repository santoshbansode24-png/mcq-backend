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
    // We use the new `classrooms` table where the teacher has created classes.
    $stmt = $pdo->prepare("
        SELECT 
            c.class_id,
            c.class_name,
            '' as division_name,
            c.class_code,
            (SELECT COUNT(*) FROM student_class_mapping scm WHERE scm.class_id = c.class_id) as student_count
        FROM classrooms c
        WHERE c.teacher_id = ?
        ORDER BY c.class_name ASC
    ");
    
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll();
    
    sendResponse('success', 'Classes fetched successfully', $classes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
