<?php
/**
 * Mark Set Completed API
 * Veeru
 * 
 * Endpoint: POST /api/mark_set_completed.php
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    sendResponse('error', 'Only POST allowed', null, 405);
}

// DEBUG: Log Request
$logData = date('Y-m-d H:i:s') . " [MARK_SET] Input: " . file_get_contents('php://input') . "\n";
file_put_contents('../debug_log.txt', $logData, FILE_APPEND);

$input = getJsonInput();
$required = ['user_id', 'chapter_id', 'type'];
$missing = validateRequired($input, $required);

// set_index can be 0, so checked manually (validateRequired treats 0 as empty)
if(!isset($input['set_index']) || $input['set_index'] === '') {
    $missing[] = 'set_index';
}

if(!empty($missing)){
    sendResponse('error', 'Missing fields: ' . implode(', ', $missing), null, 400);
}

$user_id = intval($input['user_id']);
$chapter_id = intval($input['chapter_id']);
$set_index = intval($input['set_index']);
$type = $input['type']; // 'flashcard', 'mcq'
$score = isset($input['score']) ? intval($input['score']) : 0;
$total = isset($input['total']) ? intval($input['total']) : 0;

try {
    // Upsert into content_progress
    $sql = "INSERT INTO content_progress (user_id, chapter_id, set_index, content_type, score, total, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'completed')
            ON DUPLICATE KEY UPDATE 
            score = VALUES(score),
            total = VALUES(total),
            updated_at = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $chapter_id, $set_index, $type, $score, $total]);
    
    sendResponse('success', 'Set marked completed');

} catch (PDOException $e) {
    sendResponse('error', $e->getMessage(), null, 500);
}
?>
