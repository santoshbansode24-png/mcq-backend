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

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/google_callback.php';
$client->setRedirectUri($redirectUri);

$client->addScope('email');
$client->addScope('profile');

// Redirect the user directly to Google's OAuth consent screen
$authUrl = $client->createAuthUrl();
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
?>
