<?php
$url = 'https://api.veeruapp.in/backend/api/student/get_joined_classes.php?student_id=8';
$opts = [
    'http' => [
        'method'  => 'GET',
        'ignore_errors' => true
    ]
];
$context  = stream_context_create($opts);
$result = file_get_contents($url, false, $context);
echo "RESPONSE CODE / BODY:\n" . $result . "\n";
?>
