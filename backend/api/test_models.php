<?php
require_once '../config/ai_config.php';

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=' . GEMINI_API_KEY);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

header('Content-Type: application/json');
echo $response;
?>
