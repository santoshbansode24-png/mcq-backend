<?php
require_once __DIR__ . '/../config/ai_config.php';

$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'Curl Error: ' . curl_error($ch);
} else {
    $data = json_decode($response, true);
    if (isset($data['models'])) {
        $out = "";
        foreach ($data['models'] as $model) {
            $out .= $model['name'] . "\n";
        }
        file_put_contents(__DIR__ . '/clean_models.txt', $out);
        echo "Models saved to clean_models.txt";
    } else {
        echo "No models found or error.\n";
        echo $response; // Fallback
    }
}

curl_close($ch);
?>
