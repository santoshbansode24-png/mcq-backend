<?php
/**
 * Get Students in Class API
 * MCQ Project 2.0
 * 
 * Endpoint: POST /api/teacher/get_students.php
 * Purpose: Get all students in a specific class
 */

require_once '../../config/db.php';

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
    // Get all students in the class
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            email,
            phone,
            created_at
        FROM students
        WHERE class_id = ?
        ORDER BY name ASC
    ");
    
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
    
    sendResponse('success', 'Students fetched successfully', $students, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
