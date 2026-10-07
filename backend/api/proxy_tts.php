<?php
// proxy_tts.php - Securely proxy Google TTS requests with Server-Side Permanent Cache & Natural Voices
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Veeru-Audio-Auth");

// Handle Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- SECURITY SHIELD ---
define('AUDIO_SHIELD_TOKEN', 'Veeru_Audio_Shield_2026_Secure');
$headers = function_exists('getallheaders') ? getallheaders() : [];
$providedToken = $headers['X-Veeru-Audio-Auth'] ?? ($headers['x-veeru-audio-auth'] ?? ($_SERVER['HTTP_X_VEERU_AUDIO_AUTH'] ?? ''));

if ($providedToken !== AUDIO_SHIELD_TOKEN) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error', 
        'error' => 'Unauthorized access. Secure connection required to prevent budget leaks.'
    ]);
    exit();
}
// --- END SECURITY SHIELD ---

// 1. Get Input
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

$text = isset($input['text']) ? trim($input['text']) : (isset($_REQUEST['text']) ? trim($_REQUEST['text']) : '');
$languageCode = isset($input['languageCode']) ? trim($input['languageCode']) : (isset($_REQUEST['languageCode']) ? trim($_REQUEST['languageCode']) : 'mr-IN');
$speed = isset($input['speed']) ? (float)$input['speed'] : (isset($_REQUEST['speed']) ? (float)$_REQUEST['speed'] : 0.88);

if (empty($text)) {
    echo json_encode(['error' => 'No text provided for TTS.']);
    exit();
}

// 2. Natural Voice Selection (Ultra-Natural Google Wavenet & Neural2)
$hasDevanagari = preg_match('/[\x{0900}-\x{097F}]/u', $text);
if ($hasDevanagari && $languageCode === 'en-IN') {
    $languageCode = 'mr-IN'; // Auto-switch if Marathi/Hindi script detected
}

$voiceName = 'mr-IN-Wavenet-A'; // Natural conversational female Marathi voice
$ssmlGender = 'FEMALE';

if ($languageCode === 'mr-IN') {
    $voiceName = 'mr-IN-Wavenet-A';
    $ssmlGender = 'FEMALE';
} elseif ($languageCode === 'hi-IN') {
    $voiceName = 'hi-IN-Neural2-A'; // Google's top natural Hindi voice
    $ssmlGender = 'FEMALE';
} elseif ($languageCode === 'en-IN') {
    $voiceName = 'en-IN-Neural2-A'; // Google's top natural Indian English voice
    $ssmlGender = 'FEMALE';
}

// 3. ZERO-COST PERMANENT SERVER-SIDE DISK CACHING
// Hash text + language + voice to ensure uniqueness
$cacheDir = __DIR__ . '/../../uploads/tts_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

$cacheHash = md5($text . '_' . $languageCode . '_' . $voiceName . '_' . number_format($speed, 2));
$cacheFilePath = $cacheDir . '/' . $cacheHash . '.b64';

// If already synthesized previously, return cached audio instantly (0 API calls, ₹0 cost, 2ms latency!)
if (file_exists($cacheFilePath) && filesize($cacheFilePath) > 100) {
    $cachedAudio = file_get_contents($cacheFilePath);
    echo json_encode([
        'status' => 'success',
        'source' => 'disk_cache',
        'audioContent' => $cachedAudio
    ]);
    exit();
}

// 4. Load Google Cloud TTS API Key (Only needed once per unique card)
if (file_exists('../config/secrets.php')) {
    require_once '../config/secrets.php';
}
if (file_exists('../../config/secrets.php')) {
    require_once '../../config/secrets.php';
}

if (!defined('GOOGLE_API_KEY')) {
    $envKey = getenv('GOOGLE_API_KEY');
    if ($envKey) define('GOOGLE_API_KEY', $envKey);
}

if (!defined('GOOGLE_API_KEY') || empty(GOOGLE_API_KEY)) {
    echo json_encode(['error' => 'Google API Key not configured on server.']);
    exit();
}

// 5. Call Google Cloud TTS Synthesis
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
        'speakingRate' => max(0.6, min(1.2, $speed)),
        'pitch' => 0.0
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'Connection Error: ' . $curlError]);
    exit();
}

$decoded = json_decode($response, true);

if ($httpCode !== 200 || !empty($decoded['error'])) {
    $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'TTS Synthesis Failed';
    echo json_encode(['error' => 'Google API Error: ' . $msg]);
    exit();
}

$audioBase64 = $decoded['audioContent'] ?? null;

if (!empty($audioBase64)) {
    // Save to permanent disk cache so next calls cost ₹0
    @file_put_contents($cacheFilePath, $audioBase64);
    
    echo json_encode([
        'status' => 'success',
        'source' => 'new_synthesis',
        'audioContent' => $audioBase64
    ]);
} else {
    echo json_encode(['error' => 'No audio content generated.']);
}
