<?php
// API Root Endpoint
// Returns a simple status message to confirm the API is accessible.

header('Content-Type: application/json');

// Include global CORS headers
require_once '../config/db.php';

echo json_encode([
    'status' => 'success',
    'message' => 'MCQ Project 2.0 API is running',
    'version' => '2.0.0',
    'timestamp' => date('c')
]);
?>
