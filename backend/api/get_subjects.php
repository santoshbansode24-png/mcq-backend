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

// Map classroom ID to generic class ID if applicable
try {
    $stmt_code = $pdo->prepare("SELECT class_code FROM classrooms WHERE class_id = ? LIMIT 1");
    $stmt_code->execute([$class_id]);
    $class_code = $stmt_code->fetchColumn();

    if ($class_code) {
        $stmt_generic = $pdo->prepare("SELECT class_id FROM teacher_classes WHERE class_code = ? LIMIT 1");
        $stmt_generic->execute([$class_code]);
        $mapped_class_id = $stmt_generic->fetchColumn();
        if ($mapped_class_id) {
            $class_id = (int)$mapped_class_id;
        }
    }
} catch (PDOException $e) {
    // Fail silently and use original class_id if classrooms tables don't exist
}

try {
    // Query subjects for the class with optimized stats fetching
    $stmt = $pdo->prepare("
        SELECT 
            s.subject_id,
            s.subject_name,
            s.description,
            s.class_id,
            c.class_name,
            COUNT(DISTINCT ch.chapter_id) as total_chapters,
            COUNT(m.mcq_id) as total_mcqs
        FROM subjects s
        INNER JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN chapters ch ON s.subject_id = ch.subject_id
        LEFT JOIN mcqs m ON ch.chapter_id = m.chapter_id
        WHERE s.class_id = ?
        GROUP BY s.subject_id
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
