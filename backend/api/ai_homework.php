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

    // CRITICAL: Robust AI Calling with Fallbacks
    $modelsToTry = ['gemini-2.0-flash', 'gemini-1.5-flash-latest', 'gemini-1.5-flash'];
    $finalReply = null;
    $lastError = "";
    $tokensUsed = 0;

    foreach ($modelsToTry as $model) {
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . GEMINI_API_KEY;
        
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
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 2048,
                'topP' => 0.8,
                'topK' => 40
            ],
            // Disable safety filters to prevent blocking educational images
            "safetySettings" => [
                ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
            ]
        ];

        // RETRY LOGIC for Rate Limits
        $maxRetries = 2;
        $retryCount = 0;
        
        while ($retryCount <= $maxRetries) {
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                if ($retryCount < $maxRetries) { $retryCount++; sleep(1); continue; }
                break; // Model failed, try next one
            }

            $decoded = json_decode($response, true);
            
            if ($httpCode === 200 && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                $finalReply = $decoded['candidates'][0]['content']['parts'][0]['text'];
                $tokensUsed = isset($decoded['usageMetadata']['totalTokenCount']) ? $decoded['usageMetadata']['totalTokenCount'] : 0;
                break 2; // Success!
            } else {
                $errorMsg = isset($decoded['error']['message']) ? $decoded['error']['message'] : $response;
                
                if (($httpCode === 429 || $httpCode >= 500) && $retryCount < $maxRetries) {
                    $retryCount++;
                    sleep(1);
                    continue;
                }
                
                $lastError = "Model $model failed ($httpCode): " . $errorMsg;
                error_log($lastError);
                break; // Try next model
            }
        }
    }

    if ($finalReply) {
        // TRACK USAGE
        if ($userId > 0 && $tokensUsed > 0) {
            $aiManager->logUsage($tokensUsed);
        }
        echo json_encode(['status' => 'success', 'reply' => $finalReply]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'AI Processing Failed. Please try again.', 'debug' => $lastError]);
    }

curl_close($ch);
?>
