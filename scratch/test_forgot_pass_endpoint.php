<?php
$data = json_encode(['email' => 'test@example.com', 'mobile' => '9999999999', 'security_pin' => '1234', 'new_password' => 'NewPass123']);
$opts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $data, 'ignore_errors' => true]];
$context = stream_context_create($opts);

$res1 = file_get_contents('https://api.veeruapp.in/api/forgot_password.php', false, $context);
echo "API URL (api/forgot_password.php):\n" . $res1 . "\n---\n";

$res2 = file_get_contents('https://api.veeruapp.in/forgot_password.php', false, $context);
echo "Direct Root (forgot_password.php):\n" . $res2 . "\n";
