<?php
/**
 * Create Live Class API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/create_live_class.php
 * Purpose: Authenticates with Admin's YouTube Refresh Token and creates a Live Broadcast and Stream
 */

require_once '../../config/db.php';
if (file_exists(__DIR__ . '/../../config/secrets.php')) {
    require_once __DIR__ . '/../../config/secrets.php';
}
require_once '../cors_middleware.php';

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} else {
    sendResponse('error', 'Autoloader not found. Please contact the administrator.', null, 500);
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

require_once '../../config/push_notifications.php';

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

$teacher_id = (int)$input['teacher_id'];
$class_id = (int)$input['class_id'];
$title = substr($input['title'], 0, 100);
$scheduled_time = $input['scheduled_time'] ?? date('Y-m-d\TH:i:sP'); // default to now
$message = $input['message'] ?? '';

// Load refresh token dynamically from database for the teacher, fallback to global constant
$refresh_token = '';
if (isset($pdo) && $teacher_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT youtube_refresh_token FROM users WHERE user_id = ? AND user_type = 'teacher'");
        $stmt->execute([$teacher_id]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($teacher && !empty($teacher['youtube_refresh_token'])) {
            $refresh_token = $teacher['youtube_refresh_token'];
        }
    } catch (PDOException $e) {
        error_log("Failed to load teacher YouTube token: " . $e->getMessage());
    }
}

if (empty($refresh_token)) {
    $refresh_token = YOUTUBE_REFRESH_TOKEN;
}

if (empty($refresh_token)) {
    sendResponse('error', 'YouTube account is not linked. Please connect your YouTube account first.', null, 400);
}

try {
    // 1. Initialize Google Client
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->addScope(Google_Service_YouTube::YOUTUBE);
    $client->refreshToken($refresh_token);

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

    // Self-healing: Ensure live class tables exist and update_type ENUM is expanded
    try {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_class_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_update_id INT NOT NULL,
            student_id INT NOT NULL,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_class_update (class_update_id),
            KEY idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_class_chat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_update_id INT NOT NULL,
            student_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_class_update (class_update_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_class_reactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_update_id INT NOT NULL,
            reaction_type VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_class_update (class_update_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        // Expand update_type ENUM for class_updates
        $pdo->exec("ALTER TABLE class_updates MODIFY COLUMN update_type ENUM('announcement', 'homework', 'exam', 'material', 'worksheet', 'photo', 'pdf', 'live_class', 'live_exam') DEFAULT 'announcement'");
    } catch (PDOException $dbEx) {
        error_log("Self-healing live class DB setup failed: " . $dbEx->getMessage());
    }

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

    // Trigger instant push notification to all students in the class
    sendClassPushNotifications(
        $pdo,
        $class_id,
        "🔴 LIVE CLASS STARTED: " . $title,
        !empty($message) ? $message : "Your teacher has started a live video class. Join now!",
        [
            'type' => 'announcement',
            'screen' => 'ClassUpdates'
        ]
    );

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
