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
echo "\n🧪 TESTING CONNECTIVITY (GEMINI 2.5 FLASH)...\n";
try {
    $prompt = "Hello, are you online? Respond with exactly one word: ONLINE";
    $response = callGeminiAPI($prompt);
    echo "✅ SUCCESS: " . $response . "\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    
    echo "\n🔄 ATTEMPTING FALLBACK (GEMINI FLASH LATEST)...\n";
    $fallbackUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent";
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
        echo "✅ SUCCESS: Gemini Flash Latest is working on this key.\n";
    } else {
        echo "❌ FAILED: Gemini Flash Latest also failed with code $code. Check API Key restrictions.\n";
    }
}
?>
