<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/ai_config.php';

// Function to upload file to Gemini
function uploadToGemini($filePath, $mimeType) {
    $url = "https://generativelanguage.googleapis.com/upload/v1beta/files?key=" . GEMINI_API_KEY;
    $fileSize = filesize($filePath);
    
    // 1. Initial Resumable Request
    $headers = [
        "X-Goog-Upload-Protocol: resumable",
        "X-Goog-Upload-Command: start",
        "X-Goog-Upload-Header-Content-Length: $fileSize",
        "X-Goog-Upload-Header-Content-Type: $mimeType",
        "Content-Type: application/json"
    ];
    
    $metadata = json_encode(["file" => ["display_name" => "audio_input"]]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $metadata);
    curl_setopt($ch, CURLOPT_HEADER, true); // To get response headers
    
    $response = curl_exec($ch);
    
    // Extract upload URL from headers
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    
    preg_match('/x-goog-upload-url: (.*)\r\n/i', $responseHeaders, $matches);
    $uploadUrl = isset($matches[1]) ? trim($matches[1]) : '';
    
    curl_close($ch);
    
    if (!$uploadUrl) {
        error_log("Gemini Upload Failed: No Upload URL. Response: " . $response);
        return null;
    }
    
    // 2. Upload the actual file bytes
    $fileData = file_get_contents($filePath);
    
    $headers = [
        "Content-Length: $fileSize",
        "X-Goog-Upload-Offset: 0",
        "X-Goog-Upload-Command: upload, finalize"
    ];
    
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    
    $response = curl_exec($ch);
    $json = json_decode($response, true);
    curl_close($ch);
    
    return $json['file']['uri'] ?? null;
}

// Check if it's a file upload (Audio) or text message
$inputData = json_decode(file_get_contents("php://input"), true);
$userMessage = $inputData['message'] ?? '';
$scenario = $_POST['scenario'] ?? $inputData['scenario'] ?? 'Casual Chat'; // Default to Casual
$audioUri = null;

if (isset($_FILES['audio'])) {
    $tempPath = $_FILES['audio']['tmp_name'];
    $audioUri = uploadToGemini($tempPath, $_FILES['audio']['type']);
}

if (empty($userMessage) && empty($audioUri)) {
    echo json_encode(['status' => 'error', 'message' => 'No input provided.']);
    exit;
}

// Dynamic Persona based on Scenario
$persona = "You are a friendly friend.";
if ($scenario === 'Job Interview') $persona = "You are a professional hiring manager conducting a job interview.";
if ($scenario === 'Ordering Coffee') $persona = "You are a barista at a busy coffee shop.";
if ($scenario === 'Travel') $persona = "You are an immigration officer at the airport.";
if ($scenario === 'First Date') $persona = "You are meeting the user for the first time on a date. Be charming.";

$promptText = "$persona You are having a spoken conversation with the user.
SCENARIO: $scenario.

TASK:
1. Analyze the user's input (text or audio).
2. Rate their 'Fluency Score' from 0-100 based heavily on grammar, pronunciation (if audio), and vocabulary.
3. Check for errors. If there are errors, correct them.
4. ROLEPLAY: specific to the scenario. Do NOT break character.
5. Keep your response spoken-style (short, natural, no emojis if acting professional).

Return ONLY a raw JSON object:
{
    \"has_error\": true/false,
    \"correction\": \"Corrected sentence or null\",
    \"feedback\": \"Brief explanation of error\",
    \"reply\": \"Your conversational response in character\",
    \"transcription\": \"User's text\",
    \"fluency_score\": 85
}";

$contents = [];

if ($audioUri) {
    $contents[] = [
        'role' => 'user',
        'parts' => [
            ['file_data' => ['file_uri' => $audioUri, 'mime_type' => $_FILES['audio']['type']]],
            ['text' => $promptText]
        ]
    ];
} else {
    $contents[] = [
        'role' => 'user',
        'parts' => [
            ['text' => "Student said: \"$userMessage\"\n\n" . $promptText]
        ]
    ];
}

$payload = [
    'contents' => $contents
];

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
        $aiText = $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean up markdown
        $aiText = str_replace('```json', '', $aiText);
        $aiText = str_replace('```', '', $aiText);
        $aiText = trim($aiText);

        $aiJson = json_decode($aiText, true);

        if ($aiJson) {
            echo json_encode(['status' => 'success', 'data' => $aiJson]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to parse AI response', 'raw' => $aiText]);
        }
    } else {
        error_log("Gemini API Error: " . $response);
        echo json_encode(['status' => 'error', 'message' => 'Failed to get response from AI.', 'debug' => $decodedResponse]);
    }
}

curl_close($ch);
?>
