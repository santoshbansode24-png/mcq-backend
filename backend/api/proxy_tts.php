<?php
// proxy_tts.php - Securely proxy Sarvam AI & Google TTS requests with Server-Side Permanent Disk Cache
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Veeru-Audio-Auth");

// Handle Preflight OPTIONS request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. Load API Keys from secrets.php or Environment Variables first
if (file_exists(__DIR__ . '/../../config/secrets.php')) {
    require_once __DIR__ . '/../../config/secrets.php';
}
if (file_exists(__DIR__ . '/../config/secrets.php')) {
    require_once __DIR__ . '/../config/secrets.php';
}

$sarvamKey = getenv('SARVAM_API_KEY');
if (empty($sarvamKey) && defined('SARVAM_API_KEY')) {
    $sarvamKey = SARVAM_API_KEY;
}
$sarvamKey = trim($sarvamKey ?? '');

$googleKey = getenv('GOOGLE_API_KEY');
if (empty($googleKey) && defined('GOOGLE_API_KEY')) {
    $googleKey = GOOGLE_API_KEY;
}
$googleKey = trim($googleKey ?? '');

// 2. Health & Diagnostic Check Endpoint (GET ?check=1 or ?status=1)
if (isset($_GET['check']) || isset($_GET['status']) || isset($_REQUEST['check'])) {
    $cacheDir = __DIR__ . '/../../uploads/tts_cache';
    $cacheCount = is_dir($cacheDir) ? count(glob($cacheDir . '/*.b64') ?: []) : 0;
    echo json_encode([
        'status' => 'ok',
        'sarvam_active' => !empty($sarvamKey),
        'sarvam_key_prefix' => !empty($sarvamKey) ? substr($sarvamKey, 0, 5) . '...' : null,
        'google_active' => !empty($googleKey),
        'google_key_prefix' => !empty($googleKey) ? substr($googleKey, 0, 5) . '...' : null,
        'cache_files_count' => $cacheCount,
        'server_timestamp' => date('Y-m-d H:i:s')
    ]);
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
        'error' => 'Unauthorized access. Secure connection required.'
    ]);
    exit();
}
// --- END SECURITY SHIELD ---

// 3. Get Input
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

$text = isset($input['text']) ? trim($input['text']) : (isset($_REQUEST['text']) ? trim($_REQUEST['text']) : '');
$languageCode = isset($input['languageCode']) ? trim($input['languageCode']) : (isset($_REQUEST['languageCode']) ? trim($_REQUEST['languageCode']) : 'mr-IN');
$speed = isset($input['speed']) ? (float)$input['speed'] : (isset($_REQUEST['speed']) ? (float)$_REQUEST['speed'] : 0.92);

if (empty($text)) {
    echo json_encode(['error' => 'No text provided for TTS.']);
    exit();
}

// Auto-switch to mr-IN if Devanagari detected and language was generic English
$hasDevanagari = preg_match('/[\x{0900}-\x{097F}]/u', $text);
if ($hasDevanagari && ($languageCode === 'en-IN' || $languageCode === 'en')) {
    $languageCode = 'mr-IN';
}

// 4. ZERO-COST PERMANENT SERVER-SIDE DISK CACHING
$cacheDir = __DIR__ . '/../../uploads/tts_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

$engine = (!empty($sarvamKey)) ? 'sarvam' : 'google';
$cacheHash = md5($text . '_' . $languageCode . '_' . $engine . '_' . number_format($speed, 2));
$cacheFilePath = $cacheDir . '/' . $cacheHash . '.b64';

// If already synthesized previously, return cached audio instantly (0 API calls, ₹0 cost)
if (file_exists($cacheFilePath) && filesize($cacheFilePath) > 100) {
    $cachedAudio = file_get_contents($cacheFilePath);
    echo json_encode([
        'status' => 'success',
        'source' => 'disk_cache',
        'engine' => $engine,
        'audioContent' => $cachedAudio
    ]);
    exit();
}

// =========================================================================
// 5. ENGINE 1: SARVAM AI (Ultra-Realistic Indian Regional Human Voice - bulbul:v3)
// =========================================================================
$sarvamErrorDetails = null;
if (!empty($sarvamKey)) {
    $sarvamLang = 'mr-IN';
    $speaker = 'rupali'; // Natural Indian female teacher voice
    if ($languageCode === 'hi-IN' || $languageCode === 'hi') {
        $sarvamLang = 'hi-IN';
        $speaker = 'pooja';
    } elseif ($languageCode === 'en-IN' || $languageCode === 'en') {
        $sarvamLang = 'en-IN';
        $speaker = 'kavya';
    }

    $sarvamPayload = [
        'inputs' => [$text],
        'target_language_code' => $sarvamLang,
        'speaker' => $speaker,
        'pitch' => 0,
        'pace' => max(0.7, min(1.3, $speed)),
        'loudness' => 1.2,
        'speech_sample_rate' => 22050,
        'output_audio_codec' => 'mp3',
        'enable_preprocessing' => true,
        'model' => 'bulbul:v3'
    ];

    $ch = curl_init('https://api.sarvam.ai/text-to-speech');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sarvamPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-subscription-key: ' . $sarvamKey
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $sarvamRes = curl_exec($ch);
    $sarvamHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($sarvamHttpCode === 200 && !empty($sarvamRes)) {
        $sarvamDecoded = json_decode($sarvamRes, true);
        if (!empty($sarvamDecoded['audios'][0])) {
            $audioBase64 = $sarvamDecoded['audios'][0];
            @file_put_contents($cacheFilePath, $audioBase64);
            echo json_encode([
                'status' => 'success',
                'source' => 'sarvam_ai',
                'engine' => 'sarvam',
                'audioContent' => $audioBase64
            ]);
            exit();
        }
    } else {
        $sarvamErrorDetails = "HTTP $sarvamHttpCode: " . ($sarvamRes ?: $curlErr);
        error_log("Sarvam TTS error: " . $sarvamErrorDetails);
    }
}

// =========================================================================
// 6. ENGINE 2: GOOGLE CLOUD TTS (WaveNet / Neural2 Fallback)
// =========================================================================
if (!empty($googleKey)) {
    $voiceName = 'mr-IN-Wavenet-A';
    $ssmlGender = 'FEMALE';
    if ($languageCode === 'hi-IN' || $languageCode === 'hi') {
        $voiceName = 'hi-IN-Neural2-A';
    } elseif ($languageCode === 'en-IN' || $languageCode === 'en') {
        $voiceName = 'en-IN-Neural2-A';
    }

    $googleUrl = "https://texttospeech.googleapis.com/v1/text:synthesize?key=" . $googleKey;
    $googlePayload = [
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

    $ch = curl_init($googleUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($googlePayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $googleRes = curl_exec($ch);
    $googleHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($googleHttpCode === 200) {
        $googleDecoded = json_decode($googleRes, true);
        if (!empty($googleDecoded['audioContent'])) {
            $audioBase64 = $googleDecoded['audioContent'];
            @file_put_contents($cacheFilePath, $audioBase64);
            echo json_encode([
                'status' => 'success',
                'source' => 'google_tts',
                'engine' => 'google',
                'audioContent' => $audioBase64
            ]);
            exit();
        }
    }
}

// If neither API key is active or synthesis failed:
echo json_encode([
    'status' => 'error',
    'error' => 'No TTS API configured or synthesis failed on server.',
    'sarvam_configured' => !empty($sarvamKey),
    'sarvam_error' => $sarvamErrorDetails,
    'google_configured' => !empty($googleKey),
    'message' => 'Please configure SARVAM_API_KEY or GOOGLE_API_KEY in server secrets or Railway environment variables.'
]);
