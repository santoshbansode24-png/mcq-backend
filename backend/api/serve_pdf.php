<?php
/**
 * Serve PDF - Simple and Reliable
 */

// Enable error logging (but not display) for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get file path
$file_param = $_GET['file'] ?? '';

if (empty($file_param)) {
    http_response_code(400);
    header("Content-Type: text/plain");
    die("Error: No file specified");
}

// Build full path
$base_dir = dirname(__DIR__); // /app/backend
$file_path = $base_dir . '/' . $file_param;

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    header("Content-Type: text/plain");
    die("Error: File not found\nLooking for: $file_path");
}

// Security check - ensure file is in uploads directory
if (strpos(realpath($file_path), realpath($base_dir . '/uploads')) !== 0) {
    http_response_code(403);
    header("Content-Type: text/plain");
    die("Error: Access denied");
}

// Serve the file
$filesize = filesize($file_path);
$filename = basename($file_path);

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Set headers
header('Content-Type: application/pdf');
header('Content-Length: ' . $filesize);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');

// Output file
readfile($file_path);
exit;
?>