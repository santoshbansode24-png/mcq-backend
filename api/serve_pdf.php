<?php
/**
 * Serve PDF Proxy
 * Purpose: Serve local PDF files with correct headers to bypass X-Frame-Options and CORS issues.
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// IMPORTANT: Unset X-Frame-Options to allow iframe embedding
header_remove("X-Frame-Options");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get file path from query param
$file_path = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file_path)) {
    http_response_code(400);
    die("Error: No file specified.");
}

// Security: basic directory traversal prevention
// We assume files are in the 'backend' folder structure
// $file_path comes in as 'uploads/notes/filename.pdf' typically
$base_dir = dirname(__DIR__); // Points to /backend
$full_path = $base_dir . '/' . $file_path;
$real_path = realpath($full_path);

// Verify file exists
if (!$real_path || !file_exists($real_path)) {
    http_response_code(404);
    echo "Error: File not found. <br>";
    echo "Files looking for: " . htmlspecialchars($full_path) . "<br>";
    echo "Base dir: " . $base_dir;
    exit;
}

// Verify file is within the allowed directory (optional but good practice)
if (strpos($real_path, realpath($base_dir)) !== 0) {
    http_response_code(403);
    die("Error: Access denied.");
}

// Get Mime Type
$mime_type = mime_content_type($real_path);
if (!$mime_type) {
    $mime_type = 'application/octet-stream';
}

// Set Headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($real_path));
header('Content-Disposition: inline; filename="' . basename($real_path) . '"');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Output file content
readfile($real_path);
exit;
?>
