<?php
header('Content-Type: application/json');
echo json_encode([
    'deploy_time' => date('Y-m-d H:i:s'),
    'commit' => 'backend_api_test_deploy_v2',
    'status' => 'live'
]);
?>
