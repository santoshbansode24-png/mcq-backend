<?php
/**
 * Create Live Class API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/create_live_class.php
 * Purpose: Authenticates with Admin's YouTube Refresh Token and creates a Live Broadcast and Stream
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

$required = ['teacher_id', 'class_id', 'title'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

if (!defined('YOUTUBE_REFRESH_TOKEN') || empty(YOUTUBE_REFRESH_TOKEN)) {
    sendResponse('error', 'Admin YouTube Refresh Token is not configured. Please contact the administrator.', null, 500);
}

$teacher_id = (int)$input['teacher_id'];
$class_id = (int)$input['class_id'];
$title = substr($input['title'], 0, 100);
$scheduled_time = $input['scheduled_time'] ?? date('Y-m-d\TH:i:sP'); // default to now
$message = $input['message'] ?? '';

try {
    // 1. Initialize Google Client
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->addScope(Google_Service_YouTube::YOUTUBE);
    $client->refreshToken(YOUTUBE_REFRESH_TOKEN);

    $youtube = new Google_Service_YouTube($client);

    // 2. Create a Live Broadcast
    $broadcastSnippet = new Google_Service_YouTube_LiveBroadcastSnippet();
    $broadcastSnippet->setTitle($title . " - Veeru Live Class");
    $broadcastSnippet->setScheduledStartTime($scheduled_time);

    $broadcastStatus = new Google_Service_YouTube_LiveBroadcastStatus();
    $broadcastStatus->setPrivacyStatus('unlisted'); // 'unlisted' so it only plays inside the app

    $broadcastInsert = new Google_Service_YouTube_LiveBroadcast();
    $broadcastInsert->setSnippet($broadcastSnippet);
    $broadcastInsert->setStatus($broadcastStatus);
    
    // Sometimes Google requires 'contentDetails' setup
    $broadcastDetails = new Google_Service_YouTube_LiveBroadcastContentDetails();
    $broadcastDetails->setEnableAutoStart(true);
    $broadcastDetails->setEnableAutoStop(true);
    $broadcastInsert->setContentDetails($broadcastDetails);

    $broadcastsResponse = $youtube->liveBroadcasts->insert('snippet,status,contentDetails', $broadcastInsert);
    $videoId = $broadcastsResponse->getId();

    // 3. Create a Live Stream
    $streamSnippet = new Google_Service_YouTube_LiveStreamSnippet();
    $streamSnippet->setTitle("Stream for " . $title);

    $cdn = new Google_Service_YouTube_CdnSettings();
    $cdn->setFormat("720p");
    $cdn->setIngestionType("rtmp");

    $streamInsert = new Google_Service_YouTube_LiveStream();
    $streamInsert->setSnippet($streamSnippet);
    $streamInsert->setCdn($cdn);

    $streamsResponse = $youtube->liveStreams->insert('snippet,cdn', $streamInsert);
    $streamId = $streamsResponse->getId();

    // The RTMP URL and Stream Key that the Teacher App needs
    $rtmpUrl = $streamsResponse->getCdn()->getIngestionInfo()->getIngestionAddress();
    $streamName = $streamsResponse->getCdn()->getIngestionInfo()->getStreamName();

    // 4. Bind Broadcast to Stream
    $bindResponse = $youtube->liveBroadcasts->bind(
        $videoId,
        'id,contentDetails',
        ['streamId' => $streamId]
    );

    // 5. Save the live class record in the database
    // Get teacher's school_name
    $teacherStmt = $pdo->prepare("SELECT school_name FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $teacherStmt->execute([$teacher_id]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $teacher['school_name'] ?? '';

    // Construct payload JSON string matching student app expectations
    $payload = json_encode([
        'youtube_id' => $videoId,
        'scheduled_time' => date('Y-m-d H:i:s', strtotime($scheduled_time))
    ]);

    // Insert into class_updates table
    $stmt = $pdo->prepare("
        INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload, created_at) 
        VALUES (?, ?, ?, 'live_class', ?, ?, ?, NOW())
    ");
    $stmt->execute([$teacher_id, $school_name, $class_id, $title, $message, $payload]);

    sendResponse('success', 'Live Class created on YouTube successfully', [
        'youtube_id' => $videoId,
        'rtmp_url' => $rtmpUrl,
        'stream_key' => $streamName,
        'broadcast_id' => $videoId,
        'stream_id' => $streamId
    ], 200);

} catch (Google_Service_Exception $e) {
    error_log("YouTube API Error: " . $e->getMessage());
    $errorObj = json_decode($e->getMessage());
    $errorMessage = $errorObj->error->message ?? $e->getMessage();
    sendResponse('error', 'YouTube API Error: ' . $errorMessage, null, 500);
} catch (Google_Exception $e) {
    error_log("Google Client Error: " . $e->getMessage());
    sendResponse('error', 'Google Client Error', null, 500);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse('error', 'Database error occurred', null, 500);
}
?>
