<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
function getJsonInput() { return ['class_id' => 1]; }
function sendResponse($status, $message, $data, $code) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}
require_once 'api/teacher/get_students.php';
?>
