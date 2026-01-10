<?php
// 1. Prevent JSON Errors
ob_start();

// 2. Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 3. API Key
define('GEMINI_API_KEY', 'AIzaSyCHHQqiOifU2KUpenX39imsL3qQ99wIFuw');

// 4. Helper Function to Call AI
function tryGeminiModel($modelName, $userMessage) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$modelName:generateContent?key=" . GEMINI_API_KEY;
    
    $payload = [
        "contents" => [
            ["parts" => [["text" => "You are a helpful Tutor. Keep answers concise.\n\nStudent: " . $userMessage]]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'response' => $response];
}

try {
    // 5. Get Input
    $inputJSON = file_get_contents("php://input");
    $data = json_decode($inputJSON);
    if (empty($data->message)) throw new Exception("No message provided.");

    // 6. SMART MODEL SWITCHING
    // We try these models in order. If one fails, we try the next.
    $modelsToTry = ['gemini-1.5-flash', 'gemini-pro', 'gemini-1.0-pro'];
    $finalReply = null;
    $lastError = "";

    foreach ($modelsToTry as $model) {
        $result = tryGeminiModel($model, $data->message);
        $decoded = json_decode($result['response'], true);

        // Success condition
        if ($result['code'] == 200 && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $finalReply = $decoded['candidates'][0]['content']['parts'][0]['text'];
            break; // Stop trying, we found a working model!
        } else {
            // Capture error to show if all fail
            $lastError = isset($decoded['error']['message']) ? $decoded['error']['message'] : "Unknown Error with $model";
        }
    }

    // 7. Output Result
    if ($finalReply) {
        ob_clean();
        echo json_encode(["status" => "success", "reply" => $finalReply]);
    } else {
        throw new Exception("All models failed. Last Error: " . $lastError);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>