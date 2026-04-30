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

// Only enforce limits if we have a valid user ID. 
if ($userId > 0) {
    $aiManager = new AiUsageManager($userId);
    $canProceed = $aiManager->canMakeRequest();
    if ($canProceed !== true) {
         echo "data: " . json_encode(['status' => 'error', 'message' => $canProceed]) . "\n\n";
         exit;
    }
}

$language = $_POST['language'] ?? "English";
$userText = $_POST['user_text'] ?? "";
$prompt = $_POST['prompt'] ?? "Solve this homework problem.";

if ($userText) {
    $prompt .= "\n\nSTUDENT'S TYPED QUESTION:\n\"" . $userText . "\"\n";
}

// Append Language & Style Instruction
$prompt .= "\n\nOUTPUT INSTRUCTIONS (STRICT COMPLIANCE REQUIRED):\n";
$prompt .= "1. ROLE & TONE: Act as a world-class, patient tutor for a student. Use extremely simple, easy-to-understand language. Avoid complex jargon. Assume the student is learning this for the first time.\n";
$prompt .= "2. LANGUAGE & TRANSLATION RULE: You MUST provide the EXPLANATION and SOLUTION natively in " . $language . " (use perfect Devanagari script for Hindi/Marathi).\n";
$prompt .= "   🚨 CRITICAL RULE: DO NOT translate the original question sentence itself. If the user asks an English Grammar question, KEEP the English sentence in English. Only translate the *explanation* of the rule into " . $language . ".\n";
$prompt .= "3. ANALYSIS: Carefully read the provided question or image. Identify the EXACT question being asked. Ensure your answer is 100% relevant ONLY to the core question.\n";
$prompt .= "4. STRUCTURE - Your response MUST follow this exact format:\n";
$prompt .= "   🎯 **Question Recognized:** (Write the exact original question here in its ORIGINAL language. Do NOT translate it).\n";
$prompt .= "   💡 **Core Concept (Full Definition):** (Explain the underlying rule, formula, or concept simply and thoroughly in " . $language . ").\n";
$prompt .= "   📝 **Step-by-Step Solution:** (Break the solution down into very small, numbered steps. Explain *why* you are doing each step, not just *what* you are doing. Be highly detailed and EXHAUSTIVE).\n";
$prompt .= "   ✅ **Final Answer:** (State the final result clearly in **bold**).\n";
$prompt .= "5. FORMATTING: Use Markdown for readability. Use bullet points and bold text to make it easy to scan. NEVER cut your answer short; always provide a complete, full explanation.";

$parts = [['text' => $prompt]];

if ($hasImage) {
    $file = $_FILES['image'];
    $imageData = file_get_contents($file['tmp_name']);
    $base64Image = base64_encode($imageData);
    $mimeType = $file['type'];
    
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
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

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
