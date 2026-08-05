<?php
$opts = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            'identifier' => 'test@test.com',
            'security_pin' => '0000',
            'new_password' => 'pass123'
        ]),
        'ignore_errors' => true
    ]
];
$context = stream_context_create($opts);

echo "1. Testing /api/forgot_password.php on Railway:\n";
echo file_get_contents('https://api.veeruapp.in/api/forgot_password.php', false, $context) . "\n\n";

echo "2. Testing /forgot_password.php on Railway:\n";
echo file_get_contents('https://api.veeruapp.in/forgot_password.php', false, $context) . "\n";
?>
