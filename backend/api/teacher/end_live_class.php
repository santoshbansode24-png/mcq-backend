<?php
/**
 * End Live Class API
 * Veeru
 * 
 * Endpoint: POST /api/teacher/end_live_class.php
 * Purpose: Marks a Live Class session as completed and transitions it to Recorded Video state.
 */

require_once '../../config/db.php';
if (file_exists(__DIR__ . '/../../config/secrets.php')) {
    require_once __DIR__ . '/../../config/secrets.php';
}
require_once '../cors_middleware.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

$teacher_id = isset($input['teacher_id']) ? (int)$input['teacher_id'] : 0;
$class_update_id = isset($input['class_update_id']) ? (int)$input['class_update_id'] : (isset($input['update_id']) ? (int)$input['update_id'] : 0);
$youtube_id = isset($input['youtube_id']) ? trim($input['youtube_id']) : '';

if ($class_update_id <= 0 && empty($youtube_id)) {
    sendResponse('error', 'Missing class_update_id or youtube_id', null, 400);
}

try {
    $row = null;
    if ($class_update_id > 0) {
        $stmt = $pdo->prepare("SELECT update_id, id, payload FROM class_updates WHERE (update_id = ? OR id = ?) AND update_type = 'live_class'");
        $stmt->execute([$class_update_id, $class_update_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row && !empty($youtube_id)) {
        $stmt = $pdo->prepare("SELECT update_id, id, payload FROM class_updates WHERE update_type = 'live_class' AND payload LIKE ?");
        $stmt->execute(['%"youtube_id":"' . $youtube_id . '"%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row) {
        sendResponse('error', 'Live class session not found or already ended.', null, 404);
    }

    $target_id = !empty($row['update_id']) ? $row['update_id'] : $row['id'];
    $payloadData = json_decode($row['payload'], true) ?: [];
    $payloadData['status'] = 'completed';
    $payloadData['ended'] = true;
    $payloadData['ended_at'] = date('Y-m-d H:i:s');

    $updateStmt = $pdo->prepare("UPDATE class_updates SET payload = ? WHERE update_id = ? OR id = ?");
    $updateStmt->execute([json_encode($payloadData), $target_id, $target_id]);

    sendResponse('success', 'Live Class ended successfully. The recorded video is now available for students.', [
        'class_update_id' => (int)$target_id,
        'status' => 'completed'
    ], 200);

} catch (PDOException $e) {
    error_log("Database Error in end_live_class: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
