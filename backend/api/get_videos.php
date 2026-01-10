<?php
/**
 * Get Videos API
 * Veeru
 * 
 * Endpoint: GET /api/get_videos.php?chapter_id=1
 * Purpose: Get all videos for a specific chapter
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
    // Query videos for the chapter
    $stmt = $pdo->prepare("
        SELECT 
            v.video_id,
            v.chapter_id,
            v.title,
            v.url,
            v.description,
            v.duration,
            v.created_at,
            ch.chapter_name
        FROM videos v
        INNER JOIN chapters ch ON v.chapter_id = ch.chapter_id
        WHERE v.chapter_id = ?
        ORDER BY v.created_at ASC
    ");
    
    $stmt->execute([$chapter_id]);
    $videos = $stmt->fetchAll();
    
    // Check if videos exist
    if (empty($videos)) {
        sendResponse('success', 'No videos found for this chapter', [], 200);
    }
    
    // Success response
    sendResponse('success', 'Videos retrieved successfully', $videos, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
