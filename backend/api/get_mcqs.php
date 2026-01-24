<?php
/**
 * Get MCQs API with Language Support
 * Veeru
 * 
 * Endpoint: GET /api/get_mcqs.php?chapter_id=1&medium=english
 * Purpose: Get all MCQs for a specific chapter filtered by language medium
 * 
 * Parameters:
 * - chapter_id (required): ID of the chapter
 * - medium (optional): 'english' or 'marathi' (defaults to 'english')
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get chapter_id from query parameter
$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;

// Get medium from query parameter (default to 'english')
$medium = isset($_GET['medium']) ? strtolower(trim($_GET['medium'])) : 'english';

// Validate chapter_id
if ($chapter_id <= 0) {
    sendResponse('error', 'Valid chapter_id is required', null, 400);
}

// Validate medium (only allow 'english' or 'marathi')
if (!in_array($medium, ['english', 'marathi'])) {
    sendResponse('error', 'Invalid medium. Must be "english" or "marathi"', null, 400);
}

try {
    // Query MCQs for the chapter filtered by medium
    $stmt = $pdo->prepare("
        SELECT 
            mcq_id,
            chapter_id,
            question,
            option_a,
            option_b,
            option_c,
            option_d,
            correct_answer,
            explanation,
            explanation,
            difficulty,
            medium,
            image_url
        FROM mcqs
        WHERE chapter_id = ? AND medium = ?
        ORDER BY mcq_id ASC
    ");
    
    $stmt->execute([$chapter_id, $medium]);
    $mcqs = $stmt->fetchAll();
    
    // Check if MCQs exist
    if (empty($mcqs)) {
        sendResponse('success', "No $medium MCQs found for this chapter", [], 200);
    }
    
    // Success response
    sendResponse('success', 'MCQs retrieved successfully', $mcqs, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
