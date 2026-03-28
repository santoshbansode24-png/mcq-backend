<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if (file_exists(__DIR__ . '/../config/ai_config.php')) {
    require_once __DIR__ . '/../config/ai_config.php';
}

if (!defined('GEMINI_API_KEY')) {
    $envKey = getenv('GEMINI_API_KEY');
    if ($envKey) define('GEMINI_API_KEY', $envKey);
}

if (!defined('GEMINI_API_URL')) {
    define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
}

// uploadToGemini removed for optimization

// Check if it's a file upload (Audio) or text message
$inputData = json_decode(file_get_contents("php://input"), true);
$userMessage = $inputData['message'] ?? '';
$levelId = $_POST['level_id'] ?? $inputData['level_id'] ?? 1; 
$userId = $_POST['user_id'] ?? $inputData['user_id'] ?? 0;
$audioUri = null;

require_once __DIR__ . '/../config/db.php'; // Need DB connection to fetch mission
$audioData = null;
$mimeType = null;

if (isset($_FILES['audio'])) {
    $tempPath = $_FILES['audio']['tmp_name'];
    $fileSize = $_FILES['audio']['size'];
    $fileType = $_FILES['audio']['type'];
    
    file_put_contents('ai_error.log', date('[Y-m-d H:i:s] ') . "Audio Upload: Size=$fileSize, Type=$fileType, Path=$tempPath\n", FILE_APPEND);

    // OPTIMIZATION: Inline Base64
    $audioData = base64_encode(file_get_contents($tempPath));
    $mimeType = $_FILES['audio']['type'];
} else {
     file_put_contents('ai_error.log', date('[Y-m-d H:i:s] ') . "No audio file received in \$_FILES\n", FILE_APPEND);
}

if (empty($userMessage) && empty($audioData)) {
    file_put_contents('ai_error.log', date('[Y-m-d H:i:s] ') . "Empty input (No message or audio)\n", FILE_APPEND);
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

if ($audioData) {
    $contents[] = [
        'role' => 'user',
        'parts' => [
            [
                'inlineData' => [
                    'mimeType' => $mimeType,
                    'data' => $audioData
                ]
            ],
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
// SSL FIX for XAMPP
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $errorMsg = curl_error($ch);
    file_put_contents('ai_error.log', date('[Y-m-d H:i:s] ') . "Curl Error: $errorMsg\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Curl error: ' . $errorMsg]);
} else {
    $decodedResponse = json_decode($response, true);
    
    // Log unexpected non-200 responses
    if ($httpCode !== 200) {
        file_put_contents('ai_error.log', date('[Y-m-d H:i:s] ') . "HTTP $httpCode Error: " . $response . "\n", FILE_APPEND);
    }
    
    if ($httpCode === 200 && isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])) {
        $aiText = $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean up markdown
        $aiText = str_replace('```json', '', $aiText);
        $aiText = str_replace('```', '', $aiText);
        $aiText = trim($aiText);

        $aiJson = json_decode($aiText, true);

        if ($aiJson) {
            $isGoalAchieved = $aiJson['is_goal_achieved'] ?? false;
            $fluencyScore = $aiJson['fluency_score'] ?? 0;

            // SAVE PROGRESS if goal achieved
            if ($isGoalAchieved && $userId > 0) {
                try {
                    $insertStmt = $pdo->prepare("INSERT INTO user_english_progress 
                        (user_id, level_id, is_completed, fluency_score, stars) 
                        VALUES (?, ?, 1, ?, 3) 
                        ON DUPLICATE KEY UPDATE 
                        is_completed = 1, 
                        fluency_score = GREATEST(fluency_score, VALUES(fluency_score))");
                    $insertStmt->execute([$userId, $levelId, $fluencyScore]);
                } catch (Exception $e) {
                    error_log("DB Save Error: " . $e->getMessage());
                }
            }

            // Map old 'reply' to 'tutor_speech'
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
