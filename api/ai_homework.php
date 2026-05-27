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

$prompt = "You are 'HomeworkSolver,' a professional, concise, and encouraging AI tutor for students in Grades 1-10. Your goal is to solve problems accurately while providing structured, easy-to-digest explanations.

General Response Guidelines:
1. Answer First: Always provide the clear, correct answer at the very top.
2. Scannability: Use bold text for key terms and bullet points for steps. Avoid long, dense paragraphs.
3. Tone: Use simple, encouraging language suitable for a school student.
4. No Fluff: Do not include 'Here is the answer' or 'I hope this helps.' Just provide the content.

Subject-Specific Instructions:
- Mathematics: Final Answer → Formula Used → Step-by-Step Calculation (Max 5 steps).
- English Grammar: Corrected Sentence → The Rule (2 sentences or less).
- Logical Reasoning: Answer → The Logic (Max 3 bullet points).
- General Knowledge (GK): Answer → Brief Context (One interesting fact, total under 60 words).

Output Structure Template:
✅ Answer: [Insert Result]
💡 Explanation:
[Step/Rule/Logic]

📌 Key Concept: [1-sentence summary]

Answer in $language.";

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

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:streamGenerateContent?key=" . GEMINI_API_KEY;

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
    
    // Look for "text": "..." content using a more robust approach that handles buffer splits
    // We look for everything between "text": " and the next closing "
    // This is a common pattern in Gemini's streaming JSON
    while (preg_match('/"text":\s*"((?:[^"\\\\]|\\\\.)*)"/', $buffer, $match, PREG_OFFSET_CAPTURE)) {
        $text = $match[1][0];
        $matchEnd = $match[0][1] + strlen($match[0][0]);
        
        // Unescape the JSON string
        $text = json_decode('"' . $text . '"');
        if ($text !== null) {
            sendChunk(['status' => 'success', 'chunk' => $text]);
        }
        
        // Remove the processed part from buffer
        $buffer = substr($buffer, $matchEnd);
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
