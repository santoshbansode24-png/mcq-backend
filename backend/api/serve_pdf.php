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


// 3. Range Request Support
$filesize = filesize($real_path);
$mime_type = mime_content_type($real_path) ?: 'application/pdf';

$start = 0;
$end = $filesize - 1;

// Check if http_range is sent by browser (or download manager)
if (isset($_SERVER['HTTP_RANGE'])) {
    $c_start = $start;
    $c_end = $end;

    // Extract the range string
    list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
    
    // Multiple ranges could be specified (e.g. 0-100, 200-300). We only handle the first one for simplicity.
    if (strpos($range, ',') !== false) {
        list($range, $extra_ranges) = explode(',', $range, 2);
    }
    
    // Parse the range
    if ($range == '-') {
        // The last n bytes (e.g. -500)
        $c_start = $filesize - substr($range, 1);
    } else {
        $range = explode('-', $range);
        $c_start = $range[0];
        $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $filesize - 1;
    }
    
    // Validate the range
    $c_end = ($c_end > $end) ? $end : $c_end;
    
    if ($c_start > $c_end || $c_start > $filesize - 1) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes $start-$end/$filesize");
        exit;
    }
    
    $start = $c_start;
    $end = $c_end;
    $length = $end - $start + 1;
    
    fseek($fp = fopen($real_path, 'rb'), $start);
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$filesize");
    header("Content-Length: " . $length);

} else {
    // Full content
    $fp = fopen($real_path, 'rb');
    header("Content-Length: " . $filesize);
}

// Common headers
header('Content-Type: ' . $mime_type);
$filename = basename($real_path);
// Force .pdf extension if missing, helps Android intents
if (strtolower(substr($filename, -4)) !== '.pdf') {
    $filename .= '.pdf';
}
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');

// 4. Output file content
if (ob_get_level()) ob_end_clean();

// Output the data
$buffer = 1024 * 8;
while (!feof($fp) && ($p = ftell($fp)) <= $end) {
    if ($p + $buffer > $end) {
        $buffer = $end - $p + 1;
    }
    set_time_limit(0); 
    echo fread($fp, $buffer);
    flush(); 
}

fclose($fp);
exit;