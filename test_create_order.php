<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$data = json_encode(['user_id' => 1, 'plan_id' => 1]);
file_put_contents('php://memory', $data); // Not directly possible for php://input
// Better way: use file_get_contents to hit the localhost endpoint
$response = file_get_contents('http://localhost/veeru/backend/api/create_order.php', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-type: application/json',
        'content' => $data,
        'ignore_errors' => true
    ]
]));
echo $response;
?>
