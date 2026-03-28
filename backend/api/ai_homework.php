<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Load AI config safely - works both locally (XAMPP) and on Railway
if (file_exists('../config/ai_config.php')) {
    require_once '../config/ai_config.php';
} else {
    if (!defined('GEMINI_API_KEY')) {
        $envKey = getenv('GEMINI_API_KEY');
        if ($envKey) define('GEMINI_API_KEY', $envKey);
    }
    if (!defined('GEMINI_API_URL')) {
        define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
    }
}

// Check if image file is uploaded
if (!isset($_FILES['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'No image uploaded.']);
    exit;
}

// AUTH & TRAFFIC CONTROL
require_once 'AiUsageManager.php';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0; 

// Only enforce limits if we have a valid user ID. 
if ($userId > 0) {
    $aiManager = new AiUsageManager($userId);
    $canProceed = $aiManager->canMakeRequest();
    if ($canProceed !== true) {
         echo json_encode(['status' => 'error', 'message' => $canProceed]);
         exit;
    }
}

$file = $_FILES['image'];
$language = $_POST['language'] ?? "English";
$prompt = $_POST['prompt'] ?? "Solve this homework problem.";

// Append Language & Style Instruction
$prompt .= "\n\nOUTPUT INSTRUCTIONS:\n";
$prompt .= "1. Language: Provide the solution ENTIRELY in " . $language . ". (Use Devanagari for Hindi/Marathi).\n";
$prompt .= "2. Style: Be SHORT, CLEAR, and CONCISE. Avoid unnecessary introductions or fluff. Go straight to the solution.\n";
$prompt .= "3. Format: Use bullet points or steps if needed, but keep them brief.";

// Read image data and convert to base64
$imageData = file_get_contents($file['tmp_name']);
$base64Image = base64_encode($imageData);
$mimeType = $file['type'];

try {
    // 5. Setup Streaming Headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    // 6. Gemini Configuration - STREAMING
    // Using gemini-2.0-flash as primary
    $model = 'gemini-2.0-flash';
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$model:streamGenerateContent?key=" . GEMINI_API_KEY . "&alt=sse";

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data' => $base64Image
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 2048,
        ],
        "safetySettings" => [
            ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $fullReply = "";
    $tokensUsed = 0;

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullReply, &$tokensUsed) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $jsonStr = substr($line, 6);
                $decoded = json_decode($jsonStr, true);
                
                if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                    $partText = $decoded['candidates'][0]['content']['parts'][0]['text'];
                    $fullReply .= $partText;
                    
                    echo "data: " . json_encode(["status" => "success", "chunk" => $partText]) . "\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                }

                if (isset($decoded['usageMetadata']['totalTokenCount'])) {
                    $tokensUsed = $decoded['usageMetadata']['totalTokenCount'];
                }
            }
        }
        return strlen($data);
    });

    if (ob_get_level()) ob_end_flush();
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Final Tracking
    if ($userId > 0 && $tokensUsed > 0) {
        $aiManager->logUsage($tokensUsed);
    }

    echo "data: [DONE]\n\n";
    if (ob_get_level()) ob_flush();
    flush();

} catch (Exception $e) {
    echo "data: " . json_encode(["status" => "error", "message" => $e->getMessage()]) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}
?>
