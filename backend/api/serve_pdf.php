<?php
/**
 * Serve PDF - Simple and Reliable
 */

// Enable error logging (but not display) for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function log_debug($msg) {
    file_put_contents(__DIR__ . '/../pdf_debug.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get file path
$file_param = $_GET['file'] ?? '';
log_debug("Request for file: " . $file_param);

if (empty($file_param)) {
    log_debug("Error: No file specified");
    http_response_code(400);
    header("Content-Type: text/plain");
    die("Error: No file specified");
}

// Build full path
$base_dir = dirname(__DIR__); // /app/backend
// Normalize slashes
$base_dir = str_replace('\\', '/', $base_dir);
$file_path = $base_dir . '/' . $file_param;

log_debug("Base Dir: " . $base_dir);
log_debug("Looking for Path: " . $file_path);

// Check if file exists
if (!file_exists($file_path)) {
    log_debug("Error: File not found locally. Attempting redirect to Cloudflare R2.");
    
    // Check if it's a note PDF
    if (strpos($file_param, 'uploads/notes/') !== false && strtolower(pathinfo($file_param, PATHINFO_EXTENSION)) === 'pdf') {
        $filename = basename($file_param);
        $r2_url = "https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/" . $filename;
        log_debug("Redirecting to R2: " . $r2_url);
        header("Location: " . $r2_url, true, 302);
        exit;
    }
    
    http_response_code(404);
    header("Content-Type: text/plain");
    die("Error: File not found\nLooking for: $file_path");
}

// Security check - ensure file is in uploads directory
$real_path = realpath($file_path);
$real_base = realpath($base_dir . '/uploads');

// Normalize for comparison
$real_path = str_replace('\\', '/', $real_path);
$real_base = str_replace('\\', '/', $real_base);

log_debug("Real Path: " . $real_path);
log_debug("Real Base: " . $real_base);

if (strpos($real_path, $real_base) !== 0) {
    log_debug("Error: Access denied (Security check failed)");
    http_response_code(403);
    header("Content-Type: text/plain");
    die("Error: Access denied");
}

// Serve the file
$filesize = filesize($file_path);
$filename = basename($file_path);
log_debug("Serving file: " . $filename . " (" . $filesize . " bytes)");

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}


// Disable zlib output caching/compression which can corrupt binary files
if (ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', 'Off');
}

// Set headers
header('Content-Type: application/pdf');
header('Content-Length: ' . $filesize);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');

// Output file
readfile($file_path);
log_debug("File served successfully");
exit;