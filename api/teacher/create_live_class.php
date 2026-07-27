<?php
/**
 * Create Live Class API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/create_live_class.php
 * Purpose: Creates a Live Class session using YouTube Video ID, URL, or Permanent Channel Handle (Method 1: Zero Cost, Zero Server Storage)
 */

require_once '../../config/db.php';
if (file_exists(__DIR__ . '/../../config/secrets.php')) {
    require_once __DIR__ . '/../../config/secrets.php';
}
require_once '../cors_middleware.php';
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
$youtube_input = $input['youtube_id'] ?? $input['youtube_url'] ?? $input['youtube_link'] ?? $input['youtube_handle'] ?? '';

/**
 * Helper function to extract 11-character YouTube Video ID or Handle
 */
function parseYouTubeInput($urlOrId) {
    if (empty($urlOrId)) return ['type' => 'empty', 'val' => ''];
    $urlOrId = trim($urlOrId);
    
    // Check if 11-character raw Video ID
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $urlOrId)) {
        return ['type' => 'video_id', 'val' => $urlOrId];
    }
    
    // Check for standard YouTube Video URLs
    if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|live\/))([\w-]{11})/', $urlOrId, $matches)) {
        return ['type' => 'video_id', 'val' => $matches[1]];
    }

    // Check for Channel Handle URL (e.g. youtube.com/@ChannelHandle or @ChannelHandle)
    if (preg_match('/(?:youtube\.com\/)?(@[\w\.-]+)/', $urlOrId, $handleMatches)) {
        return ['type' => 'handle', 'val' => $handleMatches[1]];
    }

    return ['type' => 'raw', 'val' => $urlOrId];
}

$parsed = parseYouTubeInput($youtube_input);

try {
    // 1. Self-healing DB setup: Ensure columns and live class tables exist
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
        // Ensure youtube_channel_handle column exists in users table
        $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'youtube_channel_handle'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE users ADD COLUMN youtube_channel_handle VARCHAR(100) DEFAULT NULL");
        }
        // Expand update_type ENUM for class_updates if needed
        $pdo->exec("ALTER TABLE class_updates MODIFY COLUMN update_type ENUM('announcement', 'homework', 'exam', 'material', 'worksheet', 'photo', 'pdf', 'live_class', 'live_exam') DEFAULT 'announcement'");
    } catch (PDOException $dbEx) {
        error_log("Self-healing live class DB setup notice: " . $dbEx->getMessage());
    }

    // 2. Fetch teacher's school_name, youtube_channel_handle, and youtube_channel_id
    $teacherStmt = $pdo->prepare("SELECT school_name, youtube_channel_id, youtube_channel_handle FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $teacherStmt->execute([$teacher_id]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    $school_name = ($teacher && !empty($teacher['school_name'])) ? $teacher['school_name'] : '';

    $videoId = '';
    $youtubeUrl = '';
    $channelHandle = '';
    $channelId = '';

    if ($parsed['type'] === 'video_id') {
        $videoId = $parsed['val'];
        $youtubeUrl = "https://www.youtube.com/watch?v=" . $videoId;
    } elseif ($parsed['type'] === 'handle') {
        $channelHandle = $parsed['val'];
        $youtubeUrl = "https://www.youtube.com/" . $channelHandle . "/live";
    } else {
        // Fallback to teacher's saved channel handle or channel ID if no input link was provided
        if (!empty($teacher['youtube_channel_handle'])) {
            $channelHandle = $teacher['youtube_channel_handle'];
            $youtubeUrl = "https://www.youtube.com/" . $channelHandle . "/live";
        } elseif (!empty($teacher['youtube_channel_id'])) {
            $channelId = $teacher['youtube_channel_id'];
            $youtubeUrl = "https://www.youtube.com/embed/live_stream?channel=" . $channelId;
        } else {
            $youtubeUrl = $parsed['val'];
        }
    }

    // 3. Construct JSON payload for student player & live activity
    $payload = json_encode([
        'youtube_id' => $videoId,
        'youtube_url' => $youtubeUrl,
        'channel_handle' => $channelHandle,
        'channel_id' => $channelId,
        'status' => 'live',
        'scheduled_time' => date('Y-m-d H:i:s', strtotime($scheduled_time))
    ]);

    // 4. Save live class update into class_updates table
    $stmt = $pdo->prepare("
        INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload, created_at) 
        VALUES (?, ?, ?, 'live_class', ?, ?, ?, NOW())
    ");
    $stmt->execute([$teacher_id, $school_name, $class_id, $title, $message, $payload]);
    $class_update_id = $pdo->lastInsertId();

    // 5. Send instant push notifications to all enrolled students in the class
    try {
        sendClassPushNotifications(
            $pdo,
            $class_id,
            "🔴 LIVE CLASS STARTED: " . $title,
            !empty($message) ? $message : "Your teacher has started a live video class. Join now to watch!",
            [
                'type' => 'live_class',
                'screen' => 'ClassUpdates',
                'class_update_id' => (int)$class_update_id,
                'youtube_id' => $videoId,
                'youtube_url' => $youtubeUrl
            ]
        );
    } catch (Exception $pushEx) {
        error_log("Push Notification Notice: " . $pushEx->getMessage());
    }

    sendResponse('success', 'Live Class session started successfully', [
        'class_update_id' => (int)$class_update_id,
        'youtube_id' => $videoId,
        'youtube_url' => $youtubeUrl,
        'channel_handle' => $channelHandle,
        'status' => 'live'
    ], 200);

} catch (PDOException $e) {
    error_log("Database Error in create_live_class: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
