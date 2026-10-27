<?php
/**
 * Get Chapters API
 * Veeru
 * 
 * Endpoint: GET /api/get_chapters.php?subject_id=1
 * Purpose: Get all chapters for a specific subject
 */

require_once __DIR__ . '/cors_middleware.php';
require_once __DIR__ . '/../config/db.php';

// Prevent caching of chapters response
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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

// Check for light query param
$is_light = isset($_GET['light']) && intval($_GET['light']) === 1;

try {
    if ($is_light) {
        // Super-fast query: Just chapters and subjects, no heavy JOINs and COUNTs
        $stmt = $pdo->prepare("
            SELECT 
                ch.chapter_id,
                ch.chapter_name,
                ch.description,
                ch.chapter_order,
                ch.subject_id,
                s.subject_name
            FROM chapters ch
            INNER JOIN subjects s ON ch.subject_id = s.subject_id
            WHERE ch.subject_id = ?
            ORDER BY ch.chapter_order ASC, ch.chapter_name ASC
        ");
    } else {
        // Highly Optimized Query: Fetch chapters and counts in a single pass
        $stmt = $pdo->prepare("
            SELECT 
                ch.chapter_id,
                ch.chapter_name,
                ch.description,
                ch.chapter_order,
                ch.subject_id,
                s.subject_name,
                (SELECT COUNT(*) FROM videos v WHERE v.chapter_id = ch.chapter_id) as total_videos,
                (SELECT COUNT(*) FROM notes n WHERE n.chapter_id = ch.chapter_id) as total_notes,
                (SELECT COUNT(*) FROM mcqs m WHERE m.chapter_id = ch.chapter_id) as total_mcqs,
                (SELECT COUNT(*) FROM flashcards f WHERE f.chapter_id = ch.chapter_id) as total_flashcards,
                (SELECT COUNT(*) FROM quick_revision qr WHERE qr.chapter_id = ch.chapter_id) as total_quick_revision
            FROM chapters ch
            INNER JOIN subjects s ON ch.subject_id = s.subject_id
            WHERE ch.subject_id = ?
            ORDER BY ch.chapter_order ASC, ch.chapter_name ASC
        ");
    }
    
    $stmt->execute([$subject_id]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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