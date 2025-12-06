<?php
/**
 * Get Notes API
 * MCQ Project 2.0
 * 
 * Endpoint: GET /api/get_notes.php?chapter_id=1
 * Purpose: Get all notes for a specific chapter
 */

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
    // Query notes for the chapter
    $stmt = $pdo->prepare("
        SELECT 
            n.note_id,
            n.chapter_id,
            n.title,
            n.file_path,
            n.content,
            n.note_type,
            n.created_at,
            ch.chapter_name
        FROM notes n
        INNER JOIN chapters ch ON n.chapter_id = ch.chapter_id
        WHERE n.chapter_id = ?
        ORDER BY n.created_at ASC
    ");
    
    $stmt->execute([$chapter_id]);
    $notes = $stmt->fetchAll();
    
    // Check if notes exist
    if (empty($notes)) {
        sendResponse('success', 'No notes found for this chapter', [], 200);
    }
    
    // Success response
    sendResponse('success', 'Notes retrieved successfully', $notes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
