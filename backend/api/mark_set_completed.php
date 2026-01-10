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

$input = getJsonInput();
$required = ['user_id', 'chapter_id', 'set_index', 'type'];
if(!empty(validateRequired($input, $required))){
    sendResponse('error', 'Missing fields', null, 400);
}

$user_id = intval($input['user_id']);
$chapter_id = intval($input['chapter_id']);
$set_index = intval($input['set_index']);
$type = $input['type']; // 'flashcard' only for now, MCQs are auto-calc

if($type === 'flashcard'){
    try {
        $sql = "INSERT IGNORE INTO flashcard_progress (user_id, chapter_id, set_index) VALUES (?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $chapter_id, $set_index]);
        sendResponse('success', 'Set marked completed');
    } catch (PDOException $e) {
        sendResponse('error', $e->getMessage(), null, 500);
    }
} else {
    // MCQs don't need manual marking, they are calculated from attempts.
    // But if we wanted to support explicit marking in future, we'd add it here.
    sendResponse('success', 'No action for this type'); 
}
?>
