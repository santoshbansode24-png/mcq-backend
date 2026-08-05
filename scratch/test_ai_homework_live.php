<?php
echo "=== TEST 1: MULTIPART FORM DATA ===\n";
$ch = curl_init('https://api.veeruapp.in/api/ai_homework.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'user_text' => 'What is 10 + 10?',
    'language' => 'English'
]);
$res1 = curl_exec($ch);
curl_close($ch);
echo $res1 . "\n\n";

echo "=== TEST 2: JSON PAYLOAD ===\n";
$ch2 = curl_init('https://api.veeruapp.in/api/ai_homework.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
    'user_text' => 'What is 10 + 10?',
    'language' => 'English'
]));
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res2 = curl_exec($ch2);
curl_close($ch2);
echo $res2 . "\n";
