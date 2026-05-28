<?php
$data = json_encode(['teacher_id' => 1, 'class_id' => 1]); // dummy data
$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/json\r\n",
        'content' => $data,
        'ignore_errors' => true // to get response even if it's 400 or 500
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents('https://api.veeruapp.in/api/teacher/delete_class.php', false, $context);
echo "RESPONSE:\n" . $result . "\n";
?>
