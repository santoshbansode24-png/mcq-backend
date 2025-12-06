<?php
/**
 * Database Configuration File
 * MCQ Project 2.0
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database Credentials (Hardcoded for Production Stability)
$db_host = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$db_name = 'test';
$db_user = 'f5vNyKym3dZo9L9.root';
$db_pass = 'bv4kAHsj6ZdW16Dx';
$db_port = '4000';

try {
    // Add Port to DSN
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    
    // SSL options for TiDB Cloud
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Always enable SSL for TiDB
    $options[PDO::MYSQL_ATTR_SSL_CA] = true;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
} catch (PDOException $e) {   
    // Return unified JSON error if connection fails
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
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
?>
