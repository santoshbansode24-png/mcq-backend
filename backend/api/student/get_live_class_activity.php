<?php
/**
 * Get Live Class Activity (Chat, Reactions & Viewer Count)
 * 
 * Endpoint: GET /api/student/get_live_class_activity.php
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

$class_update_id = isset($_GET['class_update_id']) ? intval($_GET['class_update_id']) : 0;
$last_chat_id = isset($_GET['last_chat_id']) ? intval($_GET['last_chat_id']) : 0;
$last_reaction_id = isset($_GET['last_reaction_id']) ? intval($_GET['last_reaction_id']) : 0;

if ($class_update_id <= 0) {
    sendResponse('error', 'Class Update ID is required', null, 400);
}

try {
    // 1. Fetch new chat messages
    $chatStmt = $pdo->prepare("
        SELECT id, student_id, student_name, message, created_at 
        FROM live_class_chat 
        WHERE class_update_id = ? AND id > ? 
        ORDER BY id ASC 
        LIMIT 50
    ");
    $chatStmt->execute([$class_update_id, $last_chat_id]);
    $newChats = $chatStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch new reactions
    $reactionStmt = $pdo->prepare("
        SELECT id, reaction_type 
        FROM live_class_reactions 
        WHERE class_update_id = ? AND id > ? 
        ORDER BY id ASC 
        LIMIT 100
    ");
    $reactionStmt->execute([$class_update_id, $last_reaction_id]);
    $newReactions = $reactionStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Count unique student viewers (within last 24h as a safeguard, or total sessions)
    $viewerStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id) as viewer_count 
        FROM live_class_attendance 
        WHERE class_update_id = ?
    ");
    $viewerStmt->execute([$class_update_id]);
    $viewerData = $viewerStmt->fetch(PDO::FETCH_ASSOC);
    $viewerCount = intval($viewerData['viewer_count'] ?? 0);

    sendResponse('success', 'Activity retrieved', [
        'new_chats' => $newChats,
        'new_reactions' => $newReactions,
        'viewer_count' => $viewerCount
    ], 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
