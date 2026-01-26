<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/../config/ai_config.php';

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
$levelId = $_POST['level_id'] ?? $inputData['level_id'] ?? 1; // Default to Level 1
$audioUri = null;

require_once __DIR__ . '/../config/db.php'; // Need DB connection to fetch mission

if (isset($_FILES['audio'])) {
    $tempPath = $_FILES['audio']['tmp_name'];
    $audioUri = uploadToGemini($tempPath, $_FILES['audio']['type']);
}

if (empty($userMessage) && empty($audioUri)) {
    echo json_encode(['status' => 'error', 'message' => 'No input provided.']);
    exit;
}

// Fetch Mission Data from DB
try {
    $stmt = $pdo->prepare("SELECT title, role, system_prompt FROM english_missions WHERE level_id = ?");
    $stmt->execute([$levelId]);
    $mission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mission) {
        $mission = [
            'title' => 'Unknown Mission',
            'role' => 'Tutor',
            'system_prompt' => 'Role: Tutor. Objective: Chat with student.'
        ];
    }
} catch (Exception $e) {
    // Fallback if DB fails
    $mission = ['title' => 'Error', 'role' => 'Tutor', 'system_prompt' => 'Role: Tutor.'];
}

$promptText = "{$mission['system_prompt']}

SCENARIO: {$mission['title']} ({$mission['role']}).

TASK:
1. Analyze the user's input (text or audio).
2. Rate their 'Fluency Score' from 0-100 based on the target vocabulary.
3. Check for errors. If there are errors, correct them mentally but reply gently.
4. ROLEPLAY: specific to the mission. Do NOT break character.
5. SCAFFOLDING: Use the specific hints defined in the prompt if the student struggles.
6. SUCCESS: If the user meets the prompt's success condition, set is_goal_achieved to true.

Return ONLY a raw JSON object:
{
    \"has_error\": true/false,
    \"correction\": \"Implicit correction or null\",
    \"tutor_speech\": \"Your spoken response in character\",
    \"on_screen_hint\": \"Short text for the student to read (e.g. 'Say: I want water')\",
    \"transcription\": \"User's text\",
    \"fluency_score\": 85,
    \"is_goal_achieved\": false
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
            // Map old 'reply' to 'tutor_speech' for backward compatibility if needed, using the new format
            $output = [
                'status' => 'success',
                'data' => [
                    'reply' => $aiJson['tutor_speech'], // Frontend uses 'reply' mostly
                    'tutor_speech' => $aiJson['tutor_speech'],
                    'on_screen_hint' => $aiJson['on_screen_hint'] ?? '',
                    'fluency_score' => $aiJson['fluency_score'] ?? 0,
                    'is_goal_achieved' => $aiJson['is_goal_achieved'] ?? false,
                    'transcription' => $aiJson['transcription'] ?? '',
                    'correction' => $aiJson['correction'] ?? null
                ]
            ];
            echo json_encode($output);
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
