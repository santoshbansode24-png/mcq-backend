<?php
$key = 'AIzaSyCvmd1s-1lkjyZ1g7QROmGhq_lv6kCvdFY';
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $key;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
