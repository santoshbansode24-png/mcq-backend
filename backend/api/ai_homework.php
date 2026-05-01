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
        define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');
    }
}

if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
    echo "data: " . json_encode(['status' => 'error', 'message' => 'AI Service is currently unavailable (Configuration missing).']) . "\n\n";
    exit;
}

// Check if there is neither an image nor text prompt
$hasImage = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
$hasText = isset($_POST['user_text']) && !empty(trim($_POST['user_text']));

if (!$hasImage && !$hasText) {
    echo "data: " . json_encode(['status' => 'error', 'message' => 'Please provide a text question or upload an image.']) . "\n\n";
    exit;
}

// AUTH & TRAFFIC CONTROL
require_once 'AiUsageManager.php';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0; 

// Critical Security Fix: Prevent unauthorized users from bypassing token limits
if ($userId <= 0) {
    echo "data: " . json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please log in to use the AI solver.']) . "\n\n";
    exit;
}

$aiManager = new AiUsageManager($userId);
$canProceed = $aiManager->canMakeRequest();
if ($canProceed !== true) {
     echo "data: " . json_encode(['status' => 'error', 'message' => $canProceed]) . "\n\n";
     exit;
}

$language = $_POST['language'] ?? "English";
$userText = $_POST['user_text'] ?? "";
$prompt = $_POST['prompt'] ?? "Solve this homework problem.";

if ($userText) {
    $prompt .= "\n\nSTUDENT'S TYPED QUESTION:\n\"" . $userText . "\"\n";
}

// Extract formatting and rules into System Instructions for better Gemini compliance
$sysInstruction = "Act as a world-class, encouraging personal tutor for a student. Your goal is to explain concepts clearly so the student actually learns.\n";
$sysInstruction .= "You MUST provide the EXPLANATION and SOLUTION natively in " . $language . ".\n";
$langLower = strtolower($language);
if ($langLower === 'hindi' || $langLower === 'marathi') {
    $sysInstruction .= "Ensure you use perfect Devanagari script for " . $language . ".\n";
}
$sysInstruction .= "🚨 **CRITICAL RULE:** DO NOT translate the original question itself. If it's an English Grammar question, KEEP the English sentence in English. Only translate the *explanation* into " . $language . ".\n";
$sysInstruction .= "Identify the EXACT question being asked. If the user provides a tightly cropped image containing only an equation, sentence, or word without explicit instructions, AUTOMATICALLY infer their goal (e.g., solve the equation, translate the word, explain the sentence grammar). Ensure your answer is 100% relevant ONLY to that core concept.\n";
$sysInstruction .= "🧠 **Internal Reasoning:** For Math/Science, calculate step-by-step internally. Validate every calculation. Do NOT skip algebraic steps.\n";
$sysInstruction .= "Your response MUST follow this exact format for high readability:\n\n";
$sysInstruction .= "--- \n";
$sysInstruction .= "📖 **Question Recognized:** \n> (Write the exact original question here in its ORIGINAL language)\n\n";
$sysInstruction .= "💡 **Core Concept:** \n(State the underlying concept in 1-2 sentences in " . $language . ")\n\n";
$sysInstruction .= "📝 **Step-by-Step Solution:** \n(Provide the solution in clear, numbered steps. Each step should be 1-2 sentences max. Focus ONLY on how to solve it)\n\n";
$sysInstruction .= "✅ **Final Answer:** \n** (State the final result clearly in bold) **\n";
$sysInstruction .= "--- \n\n";
$sysInstruction .= "Use Markdown for formatting. Use bold text for emphasis. Keep your entire response direct, specific, and balanced (neither too long nor too short). DO NOT add conversational filler like 'Here is your answer'.";

$parts = [['text' => $prompt]];

if ($hasImage) {
    $file = $_FILES['image'];
    
    // Safety check: Validate file size (max 10MB) to prevent memory exhaustion
    if ($file['size'] > 10485760) {
        echo "data: " . json_encode(['status' => 'error', 'message' => 'Image exceeds the 10MB size limit. Please upload a smaller image.']) . "\n\n";
        exit;
    }

    $mimeType = $file['type'];
    $validMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
    
    if (!in_array($mimeType, $validMimes)) {
        echo "data: " . json_encode(['status' => 'error', 'message' => 'Unsupported image format. Please use JPEG, PNG, or WEBP.']) . "\n\n";
        exit;
    }

    $imageData = file_get_contents($file['tmp_name']);
    $base64Image = base64_encode($imageData);
    
    $parts[] = [
        'inlineData' => [
            'mimeType' => $mimeType,
            'data' => $base64Image
        ]
    ];
}

try {
    // 5. Setup Streaming Headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    // 6. Gemini Configuration - STREAMING
    $model = 'gemini-2.5-flash';
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$model:streamGenerateContent?key=" . GEMINI_API_KEY . "&alt=sse";

    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $sysInstruction]
            ]
        ],
        'contents' => [
            [
                'parts' => $parts
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 8192,
        ],
        "safetySettings" => [
            ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
        ]
    ];

    $maxRetries = 3;
    $retryDelay = 5; // Start with 5 seconds delay
    $fullReply = "";
    $tokensUsed = 0;
    $apiError = "";
    $httpCode = 0;
    $curlError = "";

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
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
        $apiError = "";
        $buffer = "";

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullReply, &$tokensUsed, &$apiError, &$buffer) {
        $dataLength = strlen($data);
        
        // Check for direct JSON error (usually 4xx or 5xx)
        if (strpos(trim($data), '{"error":') === 0) {
            $apiError .= $data;
            return $dataLength;
        }

        $buffer .= $data;
        
        // Process complete lines from the SSE stream
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, 'data: ') === 0) {
                $jsonStr = substr($line, 6);
                if ($jsonStr === '[DONE]') continue;

                $decoded = json_decode($jsonStr, true);
                
                // Check if the API returned an error inside the SSE stream
                if (isset($decoded['error'])) {
                    $msg = $decoded['error']['message'] ?? 'AI Stream Error';
                    echo "data: " . json_encode(["status" => "error", "message" => "AI Error: " . $msg]) . "\n\n";
                    ob_flush(); flush();
                    return 0; // Stop the stream
                }

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
        return $dataLength;
    });

        if (ob_get_level()) ob_end_flush();
        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If it's a 429 Quota Error, we silently wait and retry instead of failing
        if ($httpCode === 429 && $attempt < $maxRetries) {
            sleep($retryDelay);
            $retryDelay *= 2; // Exponential backoff (5s, 10s)
            continue;
        }

        // Break out of the loop if successful or if it's a different error
        break;
    }

    // If cURL fails completely (e.g., DNS issue or strict 120s timeout)
    if ($result === false && !empty($curlError)) {
        echo "data: " . json_encode(["status" => "error", "message" => "AI Server Connection Error: " . $curlError]) . "\n\n";
        ob_flush(); flush();
        exit;
    }

    // Handle any caught API Errors that didn't stop the stream
    if (!empty($apiError)) {
        $errDecoded = json_decode($apiError, true);
        $errMsg = $errDecoded['error']['message'] ?? 'Unknown API Error';
        echo "data: " . json_encode(["status" => "error", "message" => "AI Error ($httpCode): " . $errMsg]) . "\n\n";
        ob_flush(); flush();
        exit;
    }

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
