<?php
function testLiveJoin($classCode, $studentId = 8) {
    echo "=========================================\n";
    echo "TESTING LIVE API JOIN FOR CODE: '$classCode' (Student ID: $studentId)\n";
    echo "=========================================\n";

    $url = 'https://api.veeruapp.in/backend/api/student/join_classroom.php';
    $data = json_encode([
        'student_id' => $studentId,
        'class_code' => $classCode
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $data,
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
    
    echo "HTTP Headers / Response:\n";
    echo $result . "\n\n";

    // Also test get_joined_classes
    $get_url = 'https://api.veeruapp.in/backend/api/student/get_joined_classes.php?student_id=' . $studentId;
    $joined_res = file_get_contents($get_url);
    echo "GET JOINED CLASSES RESPONSE:\n";
    echo $joined_res . "\n\n";
}

// Test with candidate codes
testLiveJoin('ODGQVB', 8);
testLiveJoin('BJVPMU', 8);
testLiveJoin('ABCDEF', 8);
?>
