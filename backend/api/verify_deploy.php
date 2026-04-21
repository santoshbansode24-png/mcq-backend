<?php
header('Content-Type: application/json');
require_once 'cors_middleware.php';

// Version and release info
$version = '4.0-Mental-Math-Optimized';
$deploy_time = '2026-04-21 12:45:00';

// Try to get the latest commit hash from git if available, or force_deploy.txt
$commit = 'unknown';
if (file_exists('../../force_deploy.txt')) {
    $lines = file('../../force_deploy.txt');
    if (isset($lines[1])) {
        $commit = trim(str_replace('Timestamp:', '', $lines[1]));
    }
}

echo json_encode([
    'status' => 'success',
    'version' => $version,
    'commit' => $commit,
    'timestamp' => $deploy_time,
    'server_time' => date('Y-m-d H:i:s'),
    'features' => [
        'mental_math_v2' => file_exists('get_math_progress.php'),
        'schema_updater' => file_exists('update_schema_maths.php')
    ]
]);
?>
