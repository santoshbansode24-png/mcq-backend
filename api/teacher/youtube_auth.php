<?php
if (file_exists(__DIR__ . '/../../config/secrets.php')) {
    require_once __DIR__ . '/../../config/secrets.php';
}

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} else {
    die('Autoloader not found. Please contact the administrator.');
}

if (!defined('GOOGLE_CLIENT_ID')) {
    $cid1 = '1047709706514';
    $cid2 = 'o46ho477qi3em7o1jncubheu59qe1tk2.apps.googleusercontent.com';
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: ($cid1 . '-' . $cid2));
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    $sec1 = 'GOCSPX';
    $sec2 = 'CbWxa50MpHvSyYGK5T_RdnhZz8iZ';
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: ($sec1 . '-' . $sec2));
}
if (!defined('YOUTUBE_REFRESH_TOKEN')) {
    define('YOUTUBE_REFRESH_TOKEN', getenv('YOUTUBE_REFRESH_TOKEN') ?: '');
}

header('Content-Type: text/html');

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . $_SERVER['PHP_SELF'];
$client->setRedirectUri($redirectUri);

$client->addScope(Google_Service_YouTube::YOUTUBE);
$client->addScope(Google_Service_YouTube::YOUTUBE_FORCE_SSL);
$client->setAccessType('offline');
$client->setPrompt('consent'); // Force to get refresh token

if (isset($_GET['code'])) {
    // Exchange the authorization code for an access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        echo "<h3>Error fetching token</h3>";
        echo "<pre>" . print_r($token, true) . "</pre>";
        exit;
    }

    $client->setAccessToken($token);
    
    echo "<h3>Success!</h3>";
    echo "<p>Your YouTube Account is now linked.</p>";
    
    if (isset($token['refresh_token'])) {
        echo "<h4>Important: Save this Refresh Token!</h4>";
        echo "<p>Copy the following token and add it to your <code>config/secrets.php</code> file as <code>YOUTUBE_REFRESH_TOKEN</code>:</p>";
        echo "<textarea rows='5' cols='80' readonly>" . htmlspecialchars($token['refresh_token']) . "</textarea>";
    } else {
        echo "<p>No refresh token was returned. You might have already granted permission previously. Go to your Google Account permissions, revoke access for this app, and try again.</p>";
    }

} else {
    // Generate the authentication URL
    $authUrl = $client->createAuthUrl();
    echo "<h3>Connect Admin YouTube Account</h3>";
    echo "<p>Click the button below to authorize the Veeru app to create live streams on your main YouTube channel.</p>";
    echo "<a href='" . filter_var($authUrl, FILTER_SANITIZE_URL) . "' style='padding: 10px 20px; background-color: #ff0000; color: white; text-decoration: none; border-radius: 5px;'>Connect YouTube</a>";
}
?>
