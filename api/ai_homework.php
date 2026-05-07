<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("X-Accel-Buffering: no"); // Disable Nginx buffering

require_once '../config/ai_config.php';

// Helper to send SSE chunks
function sendChunk($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Check if image file is uploaded OR user text is provided
$file = $_FILES['image'] ?? null;
$userText = $_POST['user_text'] ?? "";
$language = $_POST['language'] ?? "English";

if (!$file && empty($userText)) {
    sendChunk(['status' => 'error', 'message' => 'Please provide an image or text.']);
    exit;
}

$prompt = "You are Veeru, a brilliant and friendly AI tutor. Solve this homework problem step-by-step. 
Provide a clear, detailed explanation of the logic and concepts involved so the student can learn, not just copy.
If there are multiple ways to solve it, mention the simplest one first.
Answer in $language.
Use Markdown for formatting (bold, lists, etc.).";

if (!empty($userText)) {
    $prompt .= "\nAdditional User Context: " . $userText;
}

// Convert image to base64 if provided
$inlineData = null;
if ($file) {
    $imageData = file_get_contents($file['tmp_name']);
    $base64Image = base64_encode($imageData);
    $mimeType = $file['type'];
    $inlineData = [
        'mime_type' => $mimeType,
        'data' => $base64Image
    ];
}

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:streamGenerateContent?key=" . GEMINI_API_KEY;

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.4,
        'topP' => 1,
        'topK' => 32,
        'maxOutputTokens' => 2048,
    ]
];

if ($inlineData) {
    $payload['contents'][0]['parts'][] = ['inline_data' => $inlineData];
}

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // We handle output manually
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// Callback to handle streaming from Gemini and forward to client
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
    static $buffer = '';
    $buffer .= $data;
    
    // Gemini stream returns a JSON array of objects. We need to parse each object.
    // However, it's easier to just look for the text parts in the raw stream for SSE.
    // But since it's a JSON array, we can try to parse chunks.
    
    // Simplified: Find all "text": "..." patterns
    if (preg_match_all('/"text":\s*"((?:[^"\\\\]|\\\\.)*)"/', $data, $matches)) {
        foreach ($matches[1] as $text) {
            // Unescape the JSON string
            $text = json_decode('"' . $text . '"');
            sendChunk(['status' => 'success', 'chunk' => $text]);
        }
    }
    
    return strlen($data);
});

curl_exec($ch);

if (curl_errno($ch)) {
    sendChunk(['status' => 'error', 'message' => 'AI Connection Failed: ' . curl_error($ch)]);
} else {
    echo "data: [DONE]\n\n";
}

curl_close($ch);
?>
