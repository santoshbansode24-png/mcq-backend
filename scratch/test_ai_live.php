<?php
$ch = curl_init('https://api.veeruapp.in/api/test_ai_key.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "AI Test Endpoint Result:\n" . $res . "\n";
