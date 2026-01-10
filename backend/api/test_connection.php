<?php
require_once 'cors_middleware.php';
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Connection Successful', 'ip' => $_SERVER['REMOTE_ADDR']]);
?>
