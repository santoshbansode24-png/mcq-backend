<?php
$data = json_encode([
    'teacher_id' => 1, 
    'class_id' => 1, 
    'chapter_id' => 1,
    'title' => 'Test Exam',
    'duration_minutes' => 15,
    'selected_question_ids' => [1,2,3]
]);
$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/json\r\n",
        'content' => $data,
        'ignore_errors' => true
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents('https://api.veeruapp.in/api/teacher/create_live_exam.php', false, $context);
echo "RESPONSE:\n" . $result . "\n";
?>
