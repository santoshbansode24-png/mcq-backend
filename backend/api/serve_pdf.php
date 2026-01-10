<?php
/**
 * Serve PDF Proxy - Optimized for XAMPP (Windows) & Railway (Linux)
 */

// Disable error reporting to prevent corrupting PDF binary output
ini_set('display_errors', 0);
error_reporting(0);

// Allow CORS - Required for pdf.js to work
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header_remove("X-Frame-Options");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get file path from query param (e.g., ?file=uploads/notes/test.pdf)
$file_param = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file_param)) {
    header("Content-Type: text/plain");
    http_response_code(400);
    die("Error: No file specified.");
}

// Normalize paths for Windows/Linux compatibility
$base_dir = realpath(dirname(__DIR__)); 
$full_path = $base_dir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file_param);
$real_path = realpath($full_path);

// 1. Verify file exists
if (!$real_path || !file_exists($real_path)) {
    header("Content-Type: text/plain");
    http_response_code(404);
    echo "Error: File not found.\n";
    echo "Looking in: " . $full_path;
    exit;
}

// 2. Security Check: Ensure file is inside the backend folder
if (strpos($real_path, $base_dir) !== 0) {
    header("Content-Type: text/plain");
    http_response_code(403);
    die("Error: Access denied.");
}

// 3. Set Headers for PDF delivery
$mime_type = mime_content_type($real_path) ?: 'application/pdf';

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($real_path));
header('Content-Disposition: inline; filename="' . basename($real_path) . '"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');

// 4. Output file content cleanly
if (ob_get_level()) ob_end_clean();
readfile($real_path);
exit;
?>