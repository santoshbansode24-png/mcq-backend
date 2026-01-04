<?php
$url = 'http://localhost/mcq%20project2.0/backend/api/ai_chat.php';
$data = ['message' => 'Eclipse'];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "Response:\n" . $result;
?>
