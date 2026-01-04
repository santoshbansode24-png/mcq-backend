<?php
// Simple CORS Test File to check if server is reachable and headers are working
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    'status' => 'success',
    'message' => 'CORS Test Successful',
    'method' => $_SERVER['REQUEST_METHOD']
];

header('Content-Type: application/json');
echo json_encode($response);
?>
