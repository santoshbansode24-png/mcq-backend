<?php
// backend/api/test_ai_key.php
require_once '../config/ai_config.php';

header('Content-Type: application/json');

echo "Testing Gemini API Key...\n";
echo "Key in use: " . substr(GEMINI_API_KEY, 0, 5) . "..." . substr(GEMINI_API_KEY, -5) . "\n";
echo "Model URL: " . GEMINI_API_URL . "\n";

$prompt = "Hello, reply with 'API Key is working!' if you receive this.";
$response = callGeminiAPI($prompt);

if ($response) {
    echo "\nSUCCESS: API returned response:\n";
    echo $response;
} else {
    echo "\nFAILURE: API call failed.\n";
    echo "Check error_log or enable display_errors for more details.\n";
}
?>
