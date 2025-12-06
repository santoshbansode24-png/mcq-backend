<?php
/**
 * Get Classes API
 * MCQ Project 2.0
 * 
 * Endpoint: GET /api/get_classes.php
 * Purpose: Get all available classes
 */

require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

try {
    // Query all classes
    $stmt = $pdo->prepare("
        SELECT 
            class_id,
            class_name
        FROM classes
        ORDER BY class_id ASC
    ");
    
    $stmt->execute();
    $classes = $stmt->fetchAll();
    
    // Check if classes exist
    if (empty($classes)) {
        sendResponse('success', 'No classes found', [], 200);
    }
    
    // Success response
    sendResponse('success', 'Classes retrieved successfully', $classes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
