<?php
/**
 * Database Configuration File
 * Veeru
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display to prevent JSON corruption

// CORS Headers (Handled by cors_middleware.php)
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
// header("Access-Control-Allow-Private-Network: true");

// Handle preflight requests
// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//    http_response_code(200);
//    exit();
// }

// Database Credentials (Hardcoded for Production Stability)
// Database Credentials
// 1. Check for Environment Variables (Cloud)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'veeru_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASSWORD');
if ($db_pass === false) {
    $db_pass = getenv('DB_PASS');
}
if ($db_pass === false) {
    $db_pass = '';
}
$db_port = getenv('DB_PORT') ?: '3306';

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // SSL options removed to fix Railway internal network connection
    // Railway internal network is secure, and 'true' was causing a crash.
    if ($db_host !== 'localhost') {
        // Optional: Add specific SSL config here IF Railway requires it later.
        // For now, standard connection is safer.
    }
    
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
    return htmlspecialchars(strip_tags(trim($data ?? '')));
}
?>
