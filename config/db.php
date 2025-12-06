<?php
/**
 * Database Configuration File
 * MCQ Project 2.0
 * 
 * This file establishes connection to MySQL database using PDO
 */

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers - Allow requests from Android app
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
// Removed global JSON header to allow HTML pages

// Handle preflight requests
    exit();
}

/**
 * Helper function to send JSON response
 */
function sendResponse($status, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/**
 * Helper function to get JSON input
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Helper function to validate required fields
 */
function validateRequired($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Helper function to sanitize input
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Database connection is now available as $pdo
// Use this in all API files by including this file
?>
