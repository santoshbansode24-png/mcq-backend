<?php
/**
 * AI Task Status Poller
 * Allows the frontend to check if a background job is done.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }

require_once '../config/db.php';
require_once 'AiTaskManager.php';

$taskManager = new AiTaskManager($pdo);
$taskId = $_GET['task_id'] ?? 0;

if ($taskId <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid task ID"]);
    exit;
}

$task = $taskManager->getTask($taskId);

if (!$task) {
    echo json_encode(["status" => "error", "message" => "Task not found"]);
    exit;
}

// Format result_data if it's JSON
$resultData = $task['result_data'];
if ($task['status'] === 'completed' && is_string($resultData)) {
    $decoded = json_decode($resultData, true);
    if ($decoded) $resultData = $decoded;
}

echo json_encode([
    "status" => "success",
    "data" => [
        "task_id" => (int)$task['id'],
        "status" => $task['status'],
        "progress" => (int)$task['progress'],
        "result" => $resultData,
        "error" => $task['error_message']
    ]
]);
?>
