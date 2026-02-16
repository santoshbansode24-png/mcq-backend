<?php
/**
 * Get Notes API
 * Veeru
 * 
 * Endpoint: GET /api/get_notes.php?chapter_id=1
 * Purpose: Get all notes for a specific chapter
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
    // Query notes for the chapter
    $stmt = $pdo->prepare("
        SELECT 
            n.note_id,
            n.chapter_id,
            n.title,
            n.file_path,
            -- n.content, -- Optimization: Exclude heavy content, fetch only when needed
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

    // Prepare Base URL for files
    // Force HTTPS on Railway or Custom Domain (reverse proxy doesn't set HTTPS variable correctly)
    $host = $_SERVER['HTTP_HOST']; // e.g., 192.168.1.5 or localhost or api.veeruapp.in
    $is_secure_host = $host === 'api.veeruapp.in' || strpos($host, 'railway.app') !== false;
    $protocol = ($is_secure_host || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) ? "https" : "http";
    
    // Script is in /backend/api/get_notes.php -> dirname is /backend/api -> dirname again is /backend
    $backend_path = dirname(dirname($_SERVER['PHP_SELF'])); 
    $base_url = $protocol . "://" . $host . $backend_path . "/";

    // Add file_url to each note
    foreach ($notes as &$note) {
        if ($note['note_type'] === 'pdf' && !empty($note['file_path'])) {
            // Check if it's an external URL (e.g. Google Drive)
            if (strpos($note['file_path'], 'http') === 0) {
                $note['file_url'] = $note['file_path'];
            } else {
                // It's a local file.
                // OPTIMIZATION: Use direct static URL instead of serve_pdf.php proxy
                // This lets Apache handle the file serving (better ranges, caching, no PHP corruption risk)
                
                // Ensure the path uses forward slashes
                $clean_path = str_replace('\\', '/', $note['file_path']);
                
                // Construct the full URL based on the backend base URL
                // $base_url is calculated above as "http://host/veeru/backend/"
                $note['file_url'] = $base_url . $clean_path;
            }
        } else {
            $note['file_url'] = null;
        }
    }
    unset($note); // Break reference
    
    // Success response
    sendResponse('success', 'Notes retrieved successfully', $notes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
