<?php
/**
 * Get Chapters API
 * MCQ Project 2.0
 * 
 * Endpoint: GET /api/get_chapters.php?subject_id=1
 * Purpose: Get all chapters for a specific subject
 */

require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get subject_id from query parameter
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Validate subject_id
if ($subject_id <= 0) {
    sendResponse('error', 'Valid subject_id is required', null, 400);
}

try {
    // Query chapters for the subject
    $stmt = $pdo->prepare("
        SELECT 
            ch.chapter_id,
            ch.chapter_name,
            ch.description,
            ch.chapter_order,
            ch.subject_id,
            s.subject_name,
            (SELECT COUNT(*) FROM videos WHERE chapter_id = ch.chapter_id) as total_videos,
            (SELECT COUNT(*) FROM notes WHERE chapter_id = ch.chapter_id) as total_notes,
            (SELECT COUNT(*) FROM mcqs WHERE chapter_id = ch.chapter_id) as total_mcqs
        FROM chapters ch
        INNER JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE ch.subject_id = ?
        ORDER BY ch.chapter_order ASC, ch.chapter_name ASC
    ");
    
    $stmt->execute([$subject_id]);
    $chapters = $stmt->fetchAll();
    
    // Check if chapters exist
    if (empty($chapters)) {
        sendResponse('success', 'No chapters found for this subject', [], 200);
    }
    
    // Success response
    sendResponse('success', 'Chapters retrieved successfully', $chapters, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
