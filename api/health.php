<?php
// Health Check 
// Does not depend on Database
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'service' => 'Veeru Backend',
    'timestamp' => time(),
    'message' => 'Backend is reachable!'
]);
?>
