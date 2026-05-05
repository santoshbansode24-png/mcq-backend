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
            user_id as id,
            name,
            email,
            mobile,
            school_name,
            division_name,
            created_at
        FROM users
        WHERE class_id = ? AND user_type = 'student'
        ORDER BY name ASC
    ");
    
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
    
    sendResponse('success', 'Students fetched successfully', $students, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
