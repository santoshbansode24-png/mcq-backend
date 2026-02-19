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
if (file_exists('../config/ai_config.php')) {
    require_once '../config/ai_config.php';
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
    // 4. Get Input Data
    $inputJSON = file_get_contents("php://input");
    $data = json_decode($inputJSON);

    if (empty($data->message)) {
        throw new Exception("No message provided.");
    }

    // AUTH & TRAFFIC CONTROL
    require_once 'AiUsageManager.php';
    $userId = isset($data->user_id) ? (int)$data->user_id : 0;
    
    // Only enforce limits if we have a valid user ID. 
    // If user_id is missing (old app version), you might want to block or allow with strict limit.
    // For now, let's block or track on user 0 (Guest) if needed, but per plan we block.
    if ($userId > 0) {
        $aiManager = new AiUsageManager($userId);
        $canProceed = $aiManager->canMakeRequest();
        if ($canProceed !== true) {
            throw new Exception($canProceed); // Return the block message
        }
    }

    $userMessage = $data->message;

    // 5. System Instruction (The AI Persona)
    $systemInstruction = "You are a helpful and encouraging AI Tutor. 
    Explain concepts clearly to students. 
    If asked a math problem, show the steps to solve it. 
    Keep your answers concise and easy to read on a mobile screen.
    
    IMPORTANT LANGUAGE INSTRUCTION:
    - If the user writes in **Marathi**, you MUST reply in **Marathi**.
    - If the user writes in English, reply in English.
    - Always match the user's language.";

    // 6. Gemini Configuration
    // Use the URL defined in ai_config.php which has the working model (gemini-2.5-flash)
    $apiUrl = GEMINI_API_URL . "?key=" . GEMINI_API_KEY;

    // 7. Prepare Payload
    $payload = [
        "contents" => [
            [
                "parts" => [
                    // Combining system instruction + user query works best for Flash model
                    ["text" => $systemInstruction . "\n\nStudent Question: " . $userMessage]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,      // Balance between creative and accurate
            "maxOutputTokens" => 800   // Limit length for mobile UI
        ]
    ];

    // 8. Send Request via cURL
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // CRITICAL for XAMPP/Localhost: Disable SSL verification to prevent connection errors
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);

    // 9. Error Handling
    if ($curlError) {
        throw new Exception("Connection Error: " . $curlError);
    }

    $decoded = json_decode($response, true);

    // Check for API Logic Errors (like Invalid Key or Over Limit)
    if (isset($decoded['error'])) {
        $msg = $decoded['error']['message'];
        
        // Handle Quota Limit Specifically
        if (strpos($msg, 'quota') !== false || strpos($msg, '429') !== false) {
            throw new Exception("The AI is busy (Quota Limit Reached). Please wait 1 minute and try again.");
        }
        
        throw new Exception("AI API Error: " . $msg);
    }

    // 10. Extract & Return Reply
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $aiReply = $decoded['candidates'][0]['content']['parts'][0]['text'];

        // TRACK USAGE
        if ($userId > 0 && isset($decoded['usageMetadata']['totalTokenCount'])) {
            $tokensUsed = $decoded['usageMetadata']['totalTokenCount'];
            $aiManager->logUsage($tokensUsed);
        }

        // Clean Output Buffer before echoing JSON
        ob_clean();
        
        echo json_encode([
            "status" => "success",
            "reply" => $aiReply
        ]);
    } else {
        throw new Exception("No response generated by AI.");
    }

} catch (Exception $e) {
    // Clean Buffer and Return Error JSON
    ob_clean();
    // We send 200 OK even on error so the App handles it gracefully without crashing
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>