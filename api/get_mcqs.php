<?php
/**
 * Get MCQs API
 * Veeru
 * 
 * Endpoint: GET /api/get_mcqs.php?chapter_id=1
 * Purpose: Get all MCQs for a specific chapter
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get chapter_id from query parameter
$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;

// Validate chapter_id
if ($chapter_id <= 0) {
    sendResponse('error', 'Valid chapter_id is required', null, 400);
}

try {
    // Query MCQs for the chapter
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
            difficulty
        FROM mcqs
        WHERE chapter_id = ?
        ORDER BY mcq_id ASC
    ");
    
    $stmt->execute([$chapter_id]);
    $mcqs = $stmt->fetchAll();
    
    // Check if MCQs exist
    if (empty($mcqs)) {
        sendResponse('success', 'No MCQs found for this chapter', [], 200);
    }
    
    // Success response
    sendResponse('success', 'MCQs retrieved successfully', $mcqs, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
