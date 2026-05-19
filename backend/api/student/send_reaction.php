<?php
/**
 * Send Live Class Reaction API
 * 
 * Endpoint: POST /api/student/send_reaction.php
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();
$required = ['class_update_id', 'reaction_type'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$class_update_id = intval($input['class_update_id']);
$reaction_type = sanitizeInput($input['reaction_type']);

try {
    // Log the reaction
    $insertStmt = $pdo->prepare("INSERT INTO live_class_reactions (class_update_id, reaction_type) VALUES (?, ?)");
    $insertStmt->execute([$class_update_id, $reaction_type]);

    sendResponse('success', 'Reaction sent', null, 201);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
