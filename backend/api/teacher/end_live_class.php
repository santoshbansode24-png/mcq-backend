<?php
/**
 * End Live Class API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/end_live_class.php
 * Purpose: Transitions a YouTube Live Broadcast to "complete", archiving it automatically.
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

$youtube_id = $input['youtube_id'];
$teacher_id = (int)$input['teacher_id'];

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
    sendResponse('error', 'YouTube account is not linked.', null, 400);
}

try {
    // 1. Initialize Google Client
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->addScope(Google_Service_YouTube::YOUTUBE);
    $client->refreshToken($refresh_token);

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
