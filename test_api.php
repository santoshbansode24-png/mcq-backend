<?php
$data = json_encode([
    'teacher_id' => 1,
    'class_id' => 1,
    'chapter_id' => 1,
    'title' => 'Test',
    'duration_minutes' => 15,
    'selected_question_ids' => [1,2,3]
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
$result = file_get_contents('http://localhost/veeru/api/teacher/create_live_exam.php', false, $context);
echo "RESPONSE:\n" . $result . "\n";
?>
