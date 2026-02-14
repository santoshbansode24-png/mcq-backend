<?php
header('Content-Type: application/json');
echo json_encode([
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
    'HTTPS' => $_SERVER['HTTPS'] ?? 'not set',
    'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
    'REQUEST_SCHEME' => $_SERVER['REQUEST_SCHEME'] ?? 'not set',
    'remote_addr' => $_SERVER['REMOTE_ADDR'],
    'full_url_test' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
    'directory_check' => [
        'upload_dir_exists' => is_dir(__DIR__ . '/../uploads/notes'),
        'files_in_upload_dir' => glob(__DIR__ . '/../uploads/notes/*')
    ]
], JSON_PRETTY_PRINT);
?>
