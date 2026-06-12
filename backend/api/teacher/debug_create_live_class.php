<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

function reportStatus($step, $success, $message, $data = null) {
    echo json_encode([
        'step' => $step,
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]) . "\n";
    if (!$success) {
        exit;
    }
}

// Check require files existence
$files = [
    'db.php' => '../../config/db.php',
    'secrets.php' => '../../config/secrets.php',
    'cors_middleware.php' => '../cors_middleware.php',
    'autoload.php' => '../../vendor/autoload.php',
    'push_notifications.php' => '../../config/push_notifications.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            reportStatus("Require $name", true, "$name successfully required");
        } catch (Throwable $e) {
            reportStatus("Require $name", false, "Error loading $name: " . $e->getMessage());
        }
    } else {
        $is_required = ($name !== 'secrets.php');
        reportStatus("File Check $name", $name === 'secrets.php' ? true : false, "$name does not exist at path: $path (Resolved: " . realpath($path) . ")");
    }
}

// Test Google Client initialization
try {
    if (!class_exists('Google\Client')) {
        reportStatus("Google Client Check", false, "Google\\Client class does not exist!");
    }
    
    $client = new Google\Client();
    reportStatus("Google Client Instantiation", true, "Google\\Client instantiated successfully");
    
    if (!defined('GOOGLE_CLIENT_ID')) {
        reportStatus("Secrets Check", false, "GOOGLE_CLIENT_ID is not defined");
    }
    
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->addScope(Google_Service_YouTube::YOUTUBE);
    
    if (!defined('YOUTUBE_REFRESH_TOKEN')) {
        reportStatus("Secrets Check", false, "YOUTUBE_REFRESH_TOKEN is not defined");
    }
    
    $client->refreshToken(YOUTUBE_REFRESH_TOKEN);
    reportStatus("Google Client Token Refresh", true, "Token refresh executed without throwing");

    $youtube = new Google_Service_YouTube($client);
    reportStatus("YouTube Service Instantiation", true, "Google_Service_YouTube instantiated successfully");

} catch (Throwable $e) {
    reportStatus("Google API test", false, "Google API error: " . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

reportStatus("Completed", true, "All checks passed successfully");
?>
