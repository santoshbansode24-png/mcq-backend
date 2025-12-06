<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/ai_config.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->message)) {
    $userMessage = $data->message;
    
    // Basic context for the AI to behave as a tutor
    $systemInstruction = "You are a helpful and encouraging AI Tutor for students. 
    Your goal is to help them learn by explaining concepts clearly, providing examples, and asking guiding questions. 
    Do not just give the answers directly if it's a homework problem; instead, guide them to the solution. 
    Keep responses concise and easy to read on a mobile screen.";

    // Prepare the payload for Gemini
    // Note: Gemini Pro API structure
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $systemInstruction . "\n\nUser: " . $userMessage]
                ]
            ]
        ]
    ];

    // If history exists, you would structure it here, but for now we'll do single turn or append context manually
    
    $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo json_encode(['status' => 'error', 'message' => 'Curl error: ' . curl_error($ch)]);
    } else {
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode === 200 && isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])) {
            $aiReply = $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
            echo json_encode(['status' => 'success', 'reply' => $aiReply]);
        } else {
            // Log the error for debugging
            error_log("Gemini API Error: " . $response);
            echo json_encode(['status' => 'error', 'message' => 'Failed to get response from AI.', 'debug' => $decodedResponse]);
        }
    }
    
    curl_close($ch);

} else {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete data.']);
}
?>
