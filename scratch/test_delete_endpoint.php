<?php
function test_url($url) {
    echo "Testing URL: $url\n";
    $data = json_encode(['teacher_id' => 1, 'class_id' => 99999]); // dummy non-existent class to test auth/unauth response
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/json\r\n",
            'content' => $data,
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    // Check response headers for status code
    $status_line = isset($http_response_header[0]) ? $http_response_header[0] : 'Unknown';
    echo "HTTP Status: $status_line\n";
    echo "Response:\n" . $result . "\n\n";
}

test_url('https://api.veeruapp.in/api/teacher/delete_class.php');
test_url('https://api.veeruapp.in/backend/api/teacher/delete_class.php');
?>
