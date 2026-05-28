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
$chapter_ids = isset($_GET['chapter_ids']) ? $_GET['chapter_ids'] : '';

// Validate
if ($chapter_id <= 0 && empty($chapter_ids)) {
    sendResponse('error', 'Valid chapter_id or chapter_ids is required', null, 400);
}

try {
    if (!empty($chapter_ids)) {
        // Handle multiple chapters
        $ids_array = array_filter(array_map('intval', explode(',', $chapter_ids)));
        if (empty($ids_array)) {
            sendResponse('error', 'Invalid chapter_ids format', null, 400);
        }
        $inQuery = implode(',', array_fill(0, count($ids_array), '?'));
        
        $stmt = $pdo->prepare("
            SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty
            FROM mcqs
            WHERE chapter_id IN ($inQuery)
            ORDER BY mcq_id ASC
        ");
        $stmt->execute(array_values($ids_array));
    } else {
        // Handle single chapter
        $stmt = $pdo->prepare("
            SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty
            FROM mcqs
            WHERE chapter_id = ?
            ORDER BY mcq_id ASC
        ");
        $stmt->execute([$chapter_id]);
    }
    
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
