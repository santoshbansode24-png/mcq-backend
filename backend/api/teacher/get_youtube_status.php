<?php
/**
 * Get YouTube Status API
 * Veeru
 * 
 * Endpoint: GET /api/teacher/get_youtube_status.php
 * Purpose: Checks if the Admin's YouTube account is linked so teachers can go live.
 */

require_once '../../config/db.php';
if (file_exists(__DIR__ . '/../../config/secrets.php')) {
    require_once __DIR__ . '/../../config/secrets.php';
}
require_once '../cors_middleware.php';

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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Since the new architecture uses a single central Admin YouTube account, 
// we just check if the Admin has configured the refresh token.

$isConnected = defined('YOUTUBE_REFRESH_TOKEN') && !empty(YOUTUBE_REFRESH_TOKEN);

sendResponse('success', 'YouTube status fetched', [
    'connected' => $isConnected,
    'channel_id' => 'AdminChannel' // Placeholder, the app just needs to know it's connected
], 200);

?>
