<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/ai_config.php';

// Check if image file is uploaded
if (!isset($_FILES['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'No image uploaded.']);
    exit;
}

$file = $_FILES['image'];
$prompt = $_POST['prompt'] ?? "Solve this homework problem step-by-step. Explain the concepts clearly.";

// Read image data and convert to base64
$imageData = file_get_contents($file['tmp_name']);
$base64Image = base64_encode($imageData);
$mimeType = $file['type'];

// Gemini 1.5 Flash is recommended for multimodal tasks
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . GEMINI_API_KEY;

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
                [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => $base64Image
                    ]
                ]
            ]
        ]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['status' => 'error', 'message' => 'Curl error: ' . curl_error($ch)]);
} else {
    $decodedResponse = json_decode($response, true);
    
    if ($httpCode === 200 && isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])) {
        $aiReply = $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
        echo json_encode(['status' => 'success', 'reply' => $aiReply]);
    } else {
        error_log("Gemini Vision Error: " . $response);
        echo json_encode(['status' => 'error', 'message' => 'Failed to analyze image.', 'debug' => $decodedResponse]);
    }
}

curl_close($ch);
?>
