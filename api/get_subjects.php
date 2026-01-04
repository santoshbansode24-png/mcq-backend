<?php
/**
 * Get Subjects API
 * Veeru
 * 
 * Endpoint: GET /api/get_subjects.php?class_id=1
 * Purpose: Get all subjects for a specific class
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get class_id from query parameter
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Validate class_id
if ($class_id <= 0) {
    sendResponse('error', 'Valid class_id is required', null, 400);
}

try {
    // Query subjects for the class
    $stmt = $pdo->prepare("
        SELECT 
            s.subject_id,
            s.subject_name,
            s.description,
            s.class_id,
            c.class_name,
            (SELECT COUNT(*) FROM chapters WHERE subject_id = s.subject_id) as total_chapters,
            (SELECT COUNT(*) FROM mcqs m 
             INNER JOIN chapters ch ON m.chapter_id = ch.chapter_id 
             WHERE ch.subject_id = s.subject_id) as total_mcqs
        FROM subjects s
        INNER JOIN classes c ON s.class_id = c.class_id
        WHERE s.class_id = ?
        ORDER BY s.subject_name ASC
    ");
    
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll();
    
    // Check if subjects exist
    if (empty($subjects)) {
        sendResponse('success', 'No subjects found for this class', [], 200);
    }
    
    // Success response
    sendResponse('success', 'Subjects retrieved successfully', $subjects, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
