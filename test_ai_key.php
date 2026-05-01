<?php
require_once 'backend/config/ai_config.php';
try {
    echo "Using Key: " . substr(GEMINI_API_KEY, 0, 10) . "...\n";
    $response = callGeminiAPI('Say exactly: API IS WORKING');
    echo "SUCCESS: " . $response . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
