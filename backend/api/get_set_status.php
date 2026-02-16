<?php
/**
 * Get Set Status API
 * Veeru
 * 
 * Endpoint: GET /api/get_set_status.php?user_id=1&chapter_id=1&type=mcq
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'mcq'; // 'mcq' or 'flashcard'

if ($user_id <= 0 || $chapter_id <= 0) {
    sendResponse('error', 'Invalid params', [], 400);
}

// DEBUG: Log Request
$logData = date('Y-m-d H:i:s') . " [GET_STATUS] User: $user_id, Chapter: $chapter_id, Type: $type\n";
file_put_contents('../debug_log.txt', $logData, FILE_APPEND);

try {
    // Single Unified Logic for ALL content types
    // We explicitly trust the manually marked completion status from `content_progress`
    $sql = "SELECT set_index, status, score, total FROM content_progress 
            WHERE user_id = ? AND chapter_id = ? AND content_type = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $chapter_id, $type]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach($rows as $row) {
        $results[$row['set_index']] = [
            'status' => $row['status'],
            'score'  => intval($row['score']),
            'total'  => intval($row['total'])
        ];
    }

    sendResponse('success', 'Status fetched', $results);

} catch (PDOException $e) {
    sendResponse('error', $e->getMessage(), null, 500);
}
?>
