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
    $host = $_SERVER['HTTP_HOST'];
    $is_secure_host = $host === 'api.veeruapp.in' || strpos($host, 'railway.app') !== false;
    $protocol = ($is_secure_host || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) ? "https" : "http";
    
    // Calculate backend base URL
    $backend_path = dirname(dirname($_SERVER['PHP_SELF'])); 
    $base_url = $protocol . "://" . $host . rtrim($backend_path, '/') . "/";

    // Add file_url to each note
    foreach ($notes as &$note) {
        if ($note['note_type'] === 'pdf' && !empty($note['file_path'])) {
            if (strpos($note['file_path'], 'http') === 0) {
                // It's already an external URL (S3, Drive, etc.)
                $note['file_url'] = $note['file_path'];
                
                // For old apps that only open if 'drive.google.com' is present
                if (strpos($note['file_path'], 'drive.google.com') === false) {
                    $note['legacy_path'] = $note['file_path'] . (strpos($note['file_path'], '?') === false ? '?' : '&') . "_drive.google.com";
                } else {
                    $note['legacy_path'] = $note['file_path'];
                }
            } else {
                // It's a local file path (e.g., uploads/notes/file.pdf)
                $clean_path = str_replace('\\', '/', $note['file_path']);
                
                // Using serve_pdf.php as the reliable proxy for ALL local files
                // This handles CORS and Range requests better for the mobile PDF viewer
                $proxy_url = $base_url . "api/serve_pdf.php?file=" . urlencode($clean_path);
                
                $note['file_url'] = $proxy_url;
                $note['legacy_path'] = $proxy_url . "&_drive.google.com";
                
                // Also provide direct URL just in case
                $note['direct_url'] = $base_url . $clean_path;
            }
            // Overwrite original file_path with a working URL for backward compatibility
            // but prioritize the direct URL if available. 
            // Most old versions of Veeru use 'file_path' and expect it to be a URL.
            $note['file_path'] = $note['legacy_path'];
        } else {
            $note['file_url'] = null;
        }
    }
    unset($note);
    
    // Success response
    sendResponse('success', 'Notes retrieved successfully', $notes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
