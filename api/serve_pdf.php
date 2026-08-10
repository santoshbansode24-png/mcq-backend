<?php
/**
 * Serve PDF - Simple and Reliable
 */

// Enable error logging (but not display) for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function log_debug($msg) {
    @file_put_contents(__DIR__ . '/../pdf_debug.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
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

// Handle full HTTP URLs
if (strpos($file_param, 'http://') === 0 || strpos($file_param, 'https://') === 0) {
    log_debug("Redirecting external URL: " . $file_param);
    header("Location: " . $file_param, true, 302);
    exit;
}

$filename = basename($file_param);

// Always redirect uploads/notes/, uploads/class_materials/, uploads/class_documents/ to Cloudflare R2 CDN
if (strpos($file_param, 'uploads/notes/') !== false || strpos($file_param, 'notes/') === 0) {
    $r2_url = "https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/" . $filename;
    log_debug("Redirecting to R2 notes: " . $r2_url);
    header("Location: " . $r2_url, true, 302);
    exit;
}

if (strpos($file_param, 'uploads/class_materials/') !== false || strpos($file_param, 'class_materials/') === 0) {
    $r2_url = "https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/class_materials/" . $filename;
    log_debug("Redirecting to R2 class_materials: " . $r2_url);
    header("Location: " . $r2_url, true, 302);
    exit;
}

if (strpos($file_param, 'uploads/class_documents/') !== false || strpos($file_param, 'class_documents/') === 0) {
    $r2_url = "https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/class_documents/" . $filename;
    log_debug("Redirecting to R2 class_documents: " . $r2_url);
    header("Location: " . $r2_url, true, 302);
    exit;
}

// Default redirect to Cloudflare R2 notes for any other relative PDF request
$r2_url = "https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/" . $filename;
log_debug("Redirecting to R2 default: " . $r2_url);
header("Location: " . $r2_url, true, 302);
exit;
?>