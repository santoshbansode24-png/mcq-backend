<?php
/**
 * Get Teacher's Classes API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/get_classes.php
 * Purpose: Get all classes assigned to a teacher
 */

require_once '../../config/db.php';

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
    // Get all classes with student count
    $stmt = $pdo->prepare("
        SELECT 
            c.class_id,
            c.class_name,
            COUNT(DISTINCT s.id) as student_count
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.class_id
        GROUP BY c.class_id, c.class_name
        ORDER BY c.class_name ASC
    ");
    
    $stmt->execute();
    $classes = $stmt->fetchAll();
    
    sendResponse('success', 'Classes fetched successfully', $classes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
