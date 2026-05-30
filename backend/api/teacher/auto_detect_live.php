<?php
/**
 * Auto-Detect YouTube Live Broadcast & Create Class Update
 * 
 * Endpoint: POST /api/teacher/auto_detect_live.php
 */

require_once '../../config/db.php';
if (file_exists('../../config/secrets.php')) {
    require_once '../../config/secrets.php';
}
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();
$required = ['teacher_id', 'class_id', 'title', 'scheduled_time'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$teacher_id = intval($input['teacher_id']);
$class_id = intval($input['class_id']);
$title = sanitizeInput($input['title']);
$scheduled_time = sanitizeInput($input['scheduled_time']);
$message = isset($input['message']) ? sanitizeInput($input['message']) : '';

// Resolve scheduled time format
$timestamp = strtotime($scheduled_time);
if ($timestamp === false) {
    sendResponse('error', 'Invalid scheduled time format.', null, 400);
}
$formatted_scheduled_time = date('Y-m-d H:i:s', $timestamp);

try {
    // 1. Get YouTube Refresh Token
    $stmt = $pdo->prepare("SELECT school_name, youtube_refresh_token, youtube_channel_id FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        sendResponse('error', 'Teacher not found', null, 404);
    }
    
    if (empty($teacher['youtube_refresh_token'])) {
        sendResponse('error', 'YouTube account not connected. Please connect it first.', null, 400);
    }
    
    $refresh_token = $teacher['youtube_refresh_token'];
    $school_name = $teacher['school_name'];
    $channel_id = $teacher['youtube_channel_id'];
    
    // 2. Exchange Refresh Token for Access Token
    $token_url = "https://oauth2.googleapis.com/token";
    $post_data = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'refresh_token' => $refresh_token,
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        sendResponse('error', 'Failed to refresh YouTube access token', null, 500);
    }
    
    $token_res = json_decode($response, true);
    $access_token = $token_res['access_token'];
    
    // 3. Fetch Channel ID if missing
    if (empty($channel_id)) {
        $ch = curl_init("https://www.googleapis.com/youtube/v3/channels?part=id&mine=true");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $access_token,
            "Accept: application/json"
        ]);
        $channel_response = curl_exec($ch);
        curl_close($ch);
        
        $channel_data = json_decode($channel_response, true);
        if (!empty($channel_data['items'])) {
            $channel_id = $channel_data['items'][0]['id'];
            
            // Save Channel ID to DB for future speed
            $updateStmt = $pdo->prepare("UPDATE users SET youtube_channel_id = ? WHERE user_id = ?");
            $updateStmt->execute([$channel_id, $teacher_id]);
        } else {
            sendResponse('error', 'Could not locate YouTube channel ID.', null, 500);
        }
    }
    
    // 4. Query YouTube Search API for Active Live Video in Channel
    $search_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . urlencode($channel_id) . "&eventType=live&type=video";
    
    // We pass the auth bearer header to authorize the search
    $ch = curl_init($search_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Accept: application/json"
    ]);
    $search_response = curl_exec($ch);
    $search_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($search_http_code !== 200) {
        $search_err = json_decode($search_response, true);
        $msg = isset($search_err['error']['message']) ? $search_err['error']['message'] : 'Failed to search YouTube live stream';
        sendResponse('error', 'YouTube Search API Error: ' . $msg, null, 500);
    }
    
    $search_data = json_decode($search_response, true);
    if (empty($search_data['items'])) {
        // No active live stream found.
        sendResponse('warning', 'No active YouTube live broadcast detected. Please verify that you have clicked "Go Live" in your YouTube app first.', ['detected' => false]);
    }
    
    $youtube_id = $search_data['items'][0]['id']['videoId'];
    
    // 5. Insert Live Class update into database
    $payload = json_encode([
        'youtube_id' => $youtube_id,
        'scheduled_time' => $formatted_scheduled_time
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload, created_at) 
        VALUES (?, ?, ?, 'live_class', ?, ?, ?, NOW())
    ");
    $stmt->execute([$teacher_id, $school_name, $class_id, $title, $message, $payload]);
    $update_id = $pdo->lastInsertId();
    
    sendResponse('success', 'YouTube Live stream detected and linked successfully!', [
        'detected' => true,
        'youtube_id' => $youtube_id,
        'update_id' => $update_id
    ], 201);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
