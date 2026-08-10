<?php
header('Content-Type: application/json');
echo json_encode([
    'deploy_time' => date('Y-m-d H:i:s'),
    'commit' => 'test_deploy_v1',
    'status' => 'live'
]);
?>
