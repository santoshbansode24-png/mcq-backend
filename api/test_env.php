<?php
header('Content-Type: application/json');
echo json_encode([
    'getenv_HOST' => getenv('DB_HOST'),
    'ENV_HOST' => $_ENV['DB_HOST'] ?? 'not set',
    'SERVER_HOST' => $_SERVER['DB_HOST'] ?? 'not set',
    'ALL_ENV_KEYS' => array_keys($_ENV),
    'ALL_SERVER_KEYS' => array_keys($_SERVER)
]);
?>
