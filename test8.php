<?php
$data = array(
    'teacher_id' => 1,
    'class_id' => 10,
    'update_type' => 'worksheet',
    'title' => 'Simulated Worksheet',
    'message' => 'Simulated message',
    'payload' => json_encode(array("type" => "worksheet_data", "data" => "test_data_worksheet_3"))
);

$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents('http://127.0.0.1/veeru/api/teacher/upload_class_material.php', false, $context);
if ($result === FALSE) { /* Handle error */ }
var_dump($result);
