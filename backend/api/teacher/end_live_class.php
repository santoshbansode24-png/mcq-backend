<?php
/**
 * End Live Class API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/end_live_class.php
 * Purpose: Transitions a YouTube Live Broadcast to "complete", archiving it automatically.
 */

require_once '../../config/db.php';
require_once '../../config/secrets.php';
require_once '../cors_middleware.php';
require_once '../../vendor/autoload.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

$required = ['teacher_id', 'class_id', 'youtube_id'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

if (!defined('YOUTUBE_REFRESH_TOKEN') || empty(YOUTUBE_REFRESH_TOKEN)) {
    sendResponse('error', 'Admin YouTube Refresh Token is not configured.', null, 500);
}

$youtube_id = $input['youtube_id'];

try {
    // 1. Initialize Google Client
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->addScope(Google_Service_YouTube::YOUTUBE);
    $client->refreshToken(YOUTUBE_REFRESH_TOKEN);

    $youtube = new Google_Service_YouTube($client);

    // 2. Transition Broadcast to "complete"
    $response = $youtube->liveBroadcasts->transition(
        'complete', 
        $youtube_id,
        'id,status'
    );

    // 3. Mark the class update as completed in the database
    try {
        $stmt = $pdo->prepare("SELECT id, payload FROM class_updates WHERE update_type = 'live_class' AND payload LIKE ?");
        $stmt->execute(['%"youtube_id":"' . $youtube_id . '"%']);
        $row = $stmt->fetch();
        if ($row) {
            $payloadData = json_decode($row['payload'], true);
            $payloadData['status'] = 'completed';
            $payloadData['ended'] = true;
            
            $updateStmt = $pdo->prepare("UPDATE class_updates SET payload = ? WHERE id = ?");
            $updateStmt->execute([json_encode($payloadData), $row['id']]);
        }
    } catch (PDOException $dbEx) {
        error_log("Failed to mark live class update as completed: " . $dbEx->getMessage());
    }

    sendResponse('success', 'Live Class ended successfully. The video will now be processed by YouTube for archiving.', null, 200);

} catch (Google_Service_Exception $e) {
    error_log("YouTube API Error: " . $e->getMessage());
    $errorObj = json_decode($e->getMessage());
    $errorMessage = $errorObj->error->message ?? $e->getMessage();
    
    if (strpos($errorMessage, 'transitionNotAllowed') !== false) {
        // Broadcast might already be complete or hasn't started
        sendResponse('success', 'Live Class ended or was already complete.', null, 200);
    } else {
        sendResponse('error', 'YouTube API Error: ' . $errorMessage, null, 500);
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse('error', 'Server error occurred', null, 500);
}
?>
