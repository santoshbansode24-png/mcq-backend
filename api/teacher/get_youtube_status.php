<?php
/**
 * Get YouTube Status API
 * Veeru
 * 
 * Endpoint: GET /api/teacher/get_youtube_status.php
 * Purpose: Checks if the Admin's YouTube account is linked so teachers can go live.
 */

require_once '../../config/db.php';
require_once '../../config/secrets.php';
require_once '../cors_middleware.php';

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
