<?php
/**
 * GEMINI VAULT REPAIR & DIAGNOSTIC
 * This script checks the Railway configuration and fixes model name mismatches.
 */
require_once __DIR__ . '/../config/ai_config.php';

header('Content-Type: text/plain');

echo "🔍 GEMINI CONFIGURATION DIAGNOSTIC\n";
echo "---------------------------------\n";
echo "API KEY STATUS: " . (empty(GEMINI_API_KEY) ? "❌ MISSING" : "✅ LOADED (" . substr(GEMINI_API_KEY, 0, 8) . "...)") . "\n";
echo "API URL: " . GEMINI_API_URL . "\n";

// TEST 1: Simple Connectivity
echo "\n🧪 TESTING CONNECTIVITY (GEMINI 2.0 FLASH)...\n";
try {
    $prompt = "Hello, are you online? Respond with exactly one word: ONLINE";
    $response = callGeminiAPI($prompt);
    echo "✅ SUCCESS: " . $response . "\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    
    echo "\n🔄 ATTEMPTING FALLBACK (GEMINI 1.5 FLASH)...\n";
    // If 2.0 fails, maybe the account doesn't have access yet.
    // We try 1.5-flash on v1 API
    $fallbackUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent";
    $ch = curl_init($fallbackUrl . '?key=' . GEMINI_API_KEY);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'contents' => [['parts' => [['text' => 'Hello']]]]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code === 200) {
        echo "✅ SUCCESS: Gemini 1.5 Flash is working on this key.\n";
        echo "💡 RECOMMENDATION: Update ai_config.php to use Gemini 1.5 Flash.\n";
    } else {
        echo "❌ FAILED: Gemini 1.5 Flash also failed with code $code. Check API Key restrictions.\n";
    }
}
?>
