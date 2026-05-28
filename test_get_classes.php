<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['teacher_id'] = 1;
function sendResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}
require_once 'api/teacher/get_classes.php';
?>
