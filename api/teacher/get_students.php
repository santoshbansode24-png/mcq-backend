<?php
/**
 * Get Students in Class API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/get_students.php
 * Purpose: Get all students in a specific class
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
$required = ['class_id'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$class_id = (int)$input['class_id'];

try {
    // Get all students in the class from the users table
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id as id,
            u.name,
            u.email,
            u.mobile,
            u.school_name,
            c.class_name as division_name,
            scm.joined_at as created_at
        FROM users u
        JOIN student_class_mapping scm ON u.user_id = scm.student_id
        JOIN classrooms c ON scm.class_id = c.class_id
        WHERE scm.class_id = ? AND u.user_type = 'student'
        ORDER BY u.name ASC
    ");
    
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
    
    sendResponse('success', 'Students fetched successfully', $students, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
