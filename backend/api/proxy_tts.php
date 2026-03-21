<?php
// proxy_tts.php - Securely proxy Google TTS requests to hide API Key
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. Load Keys
if (file_exists('../config/secrets.php')) {
    require_once '../config/secrets.php';
}

if (!defined('GOOGLE_API_KEY')) {
    $envKey = getenv('GOOGLE_API_KEY');
    if ($envKey) define('GOOGLE_API_KEY', $envKey);
}

if (!defined('GOOGLE_API_KEY')) {
    echo json_encode(['error' => 'Google API Key not configured on server.']);
    exit();
}

// 2. Get Input
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

// Support both POST JSON and GET/POST form-data for flexibility
$text = isset($input['text']) ? $input['text'] : (isset($_REQUEST['text']) ? $_REQUEST['text'] : '');
$languageCode = isset($input['languageCode']) ? $input['languageCode'] : (isset($_REQUEST['languageCode']) ? $_REQUEST['languageCode'] : 'en-IN');
$speed = isset($input['speed']) ? $input['speed'] : (isset($_REQUEST['speed']) ? $_REQUEST['speed'] : 0.75);

if (empty($text)) {
    echo json_encode(['error' => 'No text provided for TTS.']);
    exit();
}

// 3. Prepare Voice selection logic (Moved from React Native to Backend for consistency)
$hasDevanagari = preg_match('/[\x{0900}-\x{097F}]/u', $text);
if ($hasDevanagari) {
    if ($languageCode === 'en-IN') $languageCode = 'mr-IN'; // Auto-switch if Devanagari detected
}

$voiceName = 'en-IN-Wavenet-D';
$ssmlGender = 'FEMALE';

if ($languageCode === 'mr-IN') {
    $voiceName = 'mr-IN-Wavenet-A';
} elseif ($languageCode === 'hi-IN') {
    $voiceName = 'hi-IN-Wavenet-A';
} elseif ($languageCode === 'en-IN') {
    $voiceName = 'en-IN-Wavenet-D';
}

// 4. Call Google Cloud TTS
$apiUrl = "https://texttospeech.googleapis.com/v1/text:synthesize?key=" . GOOGLE_API_KEY;

$payload = [
    'input' => ['text' => $text],
    'voice' => [
        'languageCode' => $languageCode,
        'name' => $voiceName,
        'ssmlGender' => $ssmlGender
    ],
    'audioConfig' => [
        'audioEncoding' => 'MP3',
        'speakingRate' => (float)$speed
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'Connection Error: ' . $curlError]);
    exit();
}

$decoded = json_decode($response, true);

if ($httpCode !== 200) {
    $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : $response;
    echo json_encode(['error' => 'Google API Error: ' . $msg]);
    exit();
}

// 5. Return audio content
echo json_encode([
    'status' => 'success',
    'audioContent' => $decoded['audioContent'] ?? null
]);
