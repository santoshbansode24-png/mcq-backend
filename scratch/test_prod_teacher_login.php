<?php
$data = json_encode([
    'email' => 'teacher@example.com',
    'password' => 'teacher123'
]);
$opts = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n" .
                     "Accept: application/json\r\n",
        'content' => $data,
        'ignore_errors' => true
    ]
];
$context  = stream_context_create($opts);
$result = file_get_contents('https://api.veeruapp.in/backend/api/teacher_login.php', false, $context);
echo "RESPONSE FROM backend/api/teacher_login.php:\n" . $result . "\n\n";

$result2 = file_get_contents('https://api.veeruapp.in/api/teacher_login.php', false, $context);
echo "RESPONSE FROM api/teacher_login.php:\n" . $result2 . "\n";
?>
