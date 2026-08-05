<?php
// Test live registration endpoint on Railway
$email = 'railway_iso_test_' . time() . '@example.com';

echo "1. Registering as Teacher on Railway (/backend/api/teacher_register.php):\n";
$opts1 = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            'name' => 'Railway Teacher',
            'email' => $email,
            'password' => 'Pass123!',
            'school_name' => 'Test School',
            'mobile' => '9876543210'
        ]),
        'ignore_errors' => true
    ]
];
$res1 = file_get_contents('https://api.veeruapp.in/backend/api/teacher_register.php', false, stream_context_create($opts1));
echo $res1 . "\n\n";

echo "2. Registering as Student WITH SAME EMAIL on Railway (/backend/api/register.php):\n";
$opts2 = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            'name' => 'Railway Student',
            'email' => $email,
            'password' => 'Pass123!',
            'school_name' => 'Test School',
            'mobile' => '9876543211',
            'class_id' => 1,
            'board_type' => 'CBSE'
        ]),
        'ignore_errors' => true
    ]
];
$res2 = file_get_contents('https://api.veeruapp.in/backend/api/register.php', false, stream_context_create($opts2));
echo $res2 . "\n";
?>
