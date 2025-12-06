<?php
/**
 * Database Configuration File - PRODUCTION
 * MCQ Project 2.0
 * 
 * UPDATE THESE VALUES WITH YOUR HOSTING CREDENTIALS
 */

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

// CORS Headers - Allow requests from Android app
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// PRODUCTION DATABASE CONFIGURATION
// ============================================
// TODO: Update these values with your hosting provider's credentials

define('DB_HOST', 'localhost');              // Usually 'localhost' or provided by host
define('DB_NAME', 'your_database_name');     // Database name from hosting
define('DB_USER', 'your_database_user');     // Database username
define('DB_PASS', 'your_database_password'); // Database password
define('DB_CHARSET', 'utf8mb4');

// ============================================
// EXAMPLE CONFIGURATIONS FOR POPULAR HOSTS
// ============================================

// InfinityFree Example:
// define('DB_HOST', 'sql123.infinityfree.com');
// define('DB_NAME', 'if0_12345678_mcq_project');
// define('DB_USER', 'if0_12345678');
// define('DB_PASS', 'your_password_here');

// Hostinger Example:
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'u123456789_mcq');
// define('DB_USER', 'u123456789_admin');
// define('DB_PASS', 'your_password_here');

// 000webhost Example:
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'id12345_mcq_project');
// define('DB_USER', 'id12345_admin');
// define('DB_PASS', 'your_password_here');

// ============================================

// Create PDO connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        PDO::ATTR_PERSISTENT         => true // Use persistent connections
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Log error instead of displaying in production
    error_log("Database connection failed: " . $e->getMessage());
    
    // Return generic error to user
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        'status' => 'error',
        'message' => 'Service temporarily unavailable. Please try again later.'
    ]);
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
?>
