<?php
// 1. Start Output Buffering (Prevents invisible whitespace from breaking React Native)
ob_start();

// 2. CORS & Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle Preflight Request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 3. Load Configuration
// Make sure this file exists and defines 'GEMINI_API_KEY'
// Load AI config safely - works both locally (XAMPP) and on Railway
if (file_exists(__DIR__ . '/../config/ai_config.php')) {
    require_once __DIR__ . '/../config/ai_config.php';
} else {
    // Railway: config file is gitignored, read from environment variable
    if (!defined('GEMINI_API_KEY')) {
        $envKey = getenv('GEMINI_API_KEY');
        if ($envKey) define('GEMINI_API_KEY', $envKey);
    }
    if (!defined('GEMINI_API_URL')) {
        define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
    }
}

try {
    // 4. Get Input Data (Support both JSON and FormData/Image)
    $userId = 0;
    $userMessage = "";
    $imagePart = null;

    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $inputJSON = file_get_contents("php://input");
        $data = json_decode($inputJSON);
        $userMessage = $data->message ?? "";
        $userId = isset($data->user_id) ? (int)$data->user_id : 0;
    } else {
        // Handle FormData (Mobile App with Image)
        $userMessage = $_POST['message'] ?? "";
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePart = [
                'inlineData' => [
                    'mimeType' => mime_content_type($_FILES['image']['tmp_name']),
                    'data' => base64_encode(file_get_contents($_FILES['image']['tmp_name']))
                ]
            ];
        }
    }

    if (empty($userMessage) && !$imagePart) {
        throw new Exception("No message or image provided.");
    }

    // AUTH & TRAFFIC CONTROL
    require_once __DIR__ . '/AiUsageManager.php';
    if ($userId > 0) {
        $aiManager = new AiUsageManager($userId);
        $canProceed = $aiManager->canMakeRequest();
        if ($canProceed !== true) {
            throw new Exception($canProceed);
        }
    }

    // 5. System Instruction (The AI Persona)
    $systemInstruction = "You are a helpful and encouraging AI Tutor. 
    Explain concepts clearly to students. 
    If asked a math problem, show the steps to solve it. 
    Keep your answers concise and easy to read on a mobile screen.
    
    IMPORTANT LANGUAGE INSTRUCTION:
    - If the user writes in **Marathi**, you MUST reply in **Marathi**.
    - If the user writes in English, reply in English.
    - Always match the user's language.";

    // 6. Gemini Configuration - STREAMING ENDPOINT
    $apiUrl = str_replace(':generateContent', ':streamGenerateContent', GEMINI_API_URL) . "?key=" . GEMINI_API_KEY . "&alt=sse";

    // 7. Prepare Payload
    $geminiParts = [["text" => $systemInstruction . "\n\nStudent Question: " . $userMessage]];
    if ($imagePart) {
        $geminiParts[] = $imagePart;
    }

    $payload = [
        "contents" => [
            [
                "parts" => $geminiParts
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 1000
        ]
    ];

    // 8. Send Request via cURL with Streaming
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Disable buffering for Nginx/Railway

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // We will use write function
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $fullReply = "";
    $tokensUsed = 0;

    // Define the write function to handle incoming stream chunks
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullReply, &$tokensUsed) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $jsonStr = substr($line, 6);
                $decoded = json_decode($jsonStr, true);
                
                if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                    $partText = $decoded['candidates'][0]['content']['parts'][0]['text'];
                    $fullReply .= $partText;
                    
                    // Send to client in SSE format
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

    ob_end_flush(); // Close any potential output buffering
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 9. Final Tracking (Log Usage at the end of stream)
    if ($userId > 0 && $tokensUsed > 0) {
        $aiManager->logUsage($tokensUsed);
    }

    // Signal end of stream
    echo "data: [DONE]\n\n";
    if (ob_get_level()) ob_flush();
    flush();

} catch (Exception $e) {
    // If an error occurs during streaming, try to send it as a final data packet
    echo "data: " . json_encode(["status" => "error", "message" => $e->getMessage()]) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}
?>