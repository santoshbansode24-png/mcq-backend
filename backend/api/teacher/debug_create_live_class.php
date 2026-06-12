<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$results = [];

function logStatus($step, $success, $message, $data = null) {
    global $results;
    $results[] = [
        'step' => $step,
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
}

// Check require files existence
$files = [
    'db.php' => '../../config/db.php',
    'secrets.php' => '../../config/secrets.php',
    'cors_middleware.php' => '../cors_middleware.php',
    'autoload.php' => file_exists('../../vendor/autoload.php') ? '../../vendor/autoload.php' : '../../../vendor/autoload.php',
    'push_notifications.php' => '../../config/push_notifications.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            logStatus("Require $name", true, "$name successfully required");
        } catch (Throwable $e) {
            logStatus("Require $name", false, "Error loading $name: " . $e->getMessage());
        }
    } else {
        $optional = ($name === 'secrets.php');
        logStatus("File Check $name", $optional, "$name does not exist at path: $path");
    }
}

// Search for any autoload.php in the filesystem
$found_autoloaders = [];
try {
    // Start search from /app
    if (is_dir('/app')) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/app'));
        foreach ($it as $file) {
            if ($file->isDir()) continue;
            if ($file->getFilename() === 'autoload.php') {
                $found_autoloaders[] = $file->getPathname();
            }
        }
    } else {
        $found_autoloaders[] = "/app is not a directory";
    }
} catch (Throwable $e) {
    $found_autoloaders[] = "Search error: " . $e->getMessage();
}

logStatus("Search Autoloaders", true, "Autoloader search results", $found_autoloaders);

// Test Google Client initialization if autoload exists
if (class_exists('Google\Client')) {
    try {
        $client = new Google\Client();
        logStatus("Google Client Instantiation", true, "Google\\Client instantiated successfully");
        
        $clientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : (getenv('GOOGLE_CLIENT_ID') ?: '');
        $clientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : (getenv('GOOGLE_CLIENT_SECRET') ?: '');
        $refreshToken = defined('YOUTUBE_REFRESH_TOKEN') ? YOUTUBE_REFRESH_TOKEN : (getenv('YOUTUBE_REFRESH_TOKEN') ?: '');

        logStatus("Configs Check", true, "Credentials check", [
            'client_id_set' => !empty($clientId),
            'client_secret_set' => !empty($clientSecret),
            'refresh_token_set' => !empty($refreshToken)
        ]);

        if (!empty($clientId) && !empty($clientSecret) && !empty($refreshToken)) {
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->addScope(Google_Service_YouTube::YOUTUBE);
            $client->refreshToken($refreshToken);
            logStatus("Google Client Token Refresh", true, "Token refresh executed");
        }
    } catch (Throwable $e) {
        logStatus("Google API test", false, "Google API error: " . $e->getMessage());
    }
} else {
    logStatus("Google Client Check", false, "Google\\Client class not found");
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
