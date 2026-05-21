<?php
/**
 * Get Students in Class API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/get_students.php
 * Purpose: Get only the students who explicitly joined THIS teacher's specific class using the 6-digit code.
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

// We need the classroom ID
$required = ['class_id'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

// NOTE: 'class_id' from the frontend might actually be the generic class ID if they haven't adopted the new classroom_id logic yet.
// If it's the new classroom_id from our classrooms table, great.
// If it's a teacher ID and we want all their students, we might need a different endpoint, but for now, we assume class_id is the primary key of classrooms table (classroom_id).
// Just to be safe, if frontend sends teacher_id we can filter by that as well.

$class_id = (int)$input['class_id'];
$teacher_id = isset($input['teacher_id']) ? (int)$input['teacher_id'] : null;

try {
    if ($teacher_id) {
        // Find students joined specifically to this teacher's classrooms
        $stmt = $pdo->prepare("
            SELECT DISTINCT
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
            WHERE c.teacher_id = ? AND u.user_type = 'student'
            ORDER BY u.name ASC
        ");
        $stmt->execute([$teacher_id]);
    } else {
        // Find students joined to a specific classroom
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
    }
    
    $students = $stmt->fetchAll();
    
    sendResponse('success', 'Students fetched successfully', $students, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
