<?php
/**
 * Verification script to test registration conflicts and logins
 */

function postJson($url, $data) {
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n" .
                         "Accept: application/json\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
    
    // Find HTTP status code
    $status_line = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
    $status = $match[1];
    
    return [
        'status' => $status,
        'body' => json_decode($result, true) ?: $result
    ];
}

echo "=== VERIFYING TEACHER LOGINS ===\n";

$logins = [
    ['email' => 'santoshbansode24@gmail.com', 'password' => 'veeru123'],
    ['email' => 'sbansode2021@gmail.com', 'password' => 'veeru123']
];

foreach ($logins as $login) {
    echo "Logging in {$login['email']} on backend/api/teacher_login.php:\n";
    $res = postJson('https://api.veeruapp.in/backend/api/teacher_login.php', $login);
    echo "STATUS: {$res['status']}\n";
    echo "RESPONSE: " . json_encode($res['body']) . "\n\n";

    echo "Logging in {$login['email']} on api/teacher_login.php:\n";
    $res2 = postJson('https://api.veeruapp.in/api/teacher_login.php', $login);
    echo "STATUS: {$res2['status']}\n";
    echo "RESPONSE: " . json_encode($res2['body']) . "\n\n";
}

echo "=== VERIFYING REGISTRATION CONFLICTS ===\n";

// Trying to register a teacher with an email that is already a teacher (e.g. santoshbansode24@gmail.com)
$teacherReg = [
    'name' => 'Santosh Teacher',
    'email' => 'santoshbansode24@gmail.com',
    'password' => 'password123',
    'school_name' => 'Santosh Academy'
];

echo "Registering teacher on backend/api/teacher_register.php with existing email:\n";
$res3 = postJson('https://api.veeruapp.in/backend/api/teacher_register.php', $teacherReg);
echo "STATUS: {$res3['status']}\n";
echo "RESPONSE: " . json_encode($res3['body']) . "\n\n";

echo "Registering teacher on api/teacher_register.php with existing email:\n";
$res4 = postJson('https://api.veeruapp.in/api/teacher_register.php', $teacherReg);
echo "STATUS: {$res4['status']}\n";
echo "RESPONSE: " . json_encode($res4['body']) . "\n\n";

// Trying to register a student with an email that is already a teacher (e.g. santoshbansode24@gmail.com)
$studentReg = [
    'name' => 'Santosh Student',
    'email' => 'santoshbansode24@gmail.com',
    'mobile' => '9999988888',
    'password' => 'password123',
    'school_name' => 'Santosh Academy',
    'class_id' => 1,
    'board_type' => 'CBSE'
];

echo "Registering student on backend/api/register.php with existing email:\n";
$res5 = postJson('https://api.veeruapp.in/backend/api/register.php', $studentReg);
echo "STATUS: {$res5['status']}\n";
echo "RESPONSE: " . json_encode($res5['body']) . "\n\n";

echo "Registering student on api/register.php with existing email:\n";
$res6 = postJson('https://api.veeruapp.in/api/register.php', $studentReg);
echo "STATUS: {$res6['status']}\n";
echo "RESPONSE: " . json_encode($res6['body']) . "\n\n";
?>
