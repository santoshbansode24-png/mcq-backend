<?php
/**
 * Database Configuration File
 * Veeru
 */

// Force UTC timezone globally in PHP
date_default_timezone_set('UTC');

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
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
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
        PDO::ATTR_TIMEOUT            => 5, // Fast-fail after 5 seconds to prevent 502 Bad Gateway
        PDO::ATTR_PERSISTENT         => true, // Phase 2 Optimization: Huge latency reduction via connection pooling
    ];

    // SSL options removed to fix Railway internal network connection
    // Railway internal network is secure, and 'true' was causing a crash.
    if ($db_host !== 'localhost') {
        // Optional: Add specific SSL config here IF Railway requires it later.
        // For now, standard connection is safer.
    }
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // Force MySQL connection session to use UTC
    $pdo->exec("SET time_zone = '+00:00'");

    // Auto-verify claim_token column on pdf_study_jobs
    try {
        $pdo->exec("ALTER TABLE `pdf_study_jobs` ADD COLUMN `claim_token` VARCHAR(64) NULL AFTER `progress` ");
    } catch (Throwable $e) {
        // Ignored if column already exists
    }
    
    // session-based packet size increase for PDF handling (if user lacks global PERMISSION)
    try {
        $pdo->exec("SET SESSION max_allowed_packet = 104857600"); // 100MB
    } catch (Exception $e) {
        // Silently fail if not supported, but log it
        error_log("DB session packet size increase failed: " . $e->getMessage());
    }
    
} catch (PDOException $e) {   
    // Inject CORS headers to prevent clients blocking error response
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Private-Network: true");

    // Return unified JSON error if connection fails
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

/**
 * Helper function to send JSON response
 */
function sendResponse($status, $message, $data = null, $httpCode = 200) {
    // Inject CORS headers to prevent clients blocking error/success responses
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Private-Network: true");

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
 * Helper function to convert encoding to UTF-8
 * Handles Windows-1252 (common in Excel CSVs) to UTF-8
 */
function convertUtf8($data) {
    if (is_array($data)) {
        return array_map('convertUtf8', $data);
    }
    
    // If it's already valid UTF-8, return it
    if (mb_check_encoding($data, 'UTF-8')) {
        return $data;
    }
    
    // Otherwise, assume it's Windows-1252 (or ISO-8859-1) and convert
    return mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
}

/**
 * Helper function to sanitize input
 */
function sanitizeInput($data) {
    return strip_tags(trim($data ?? ''));
}
?>
