<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/config/ai_config.php';

echo "Testing Gemini API...\n";
try {
    $result = callGeminiAPI("Say exactly: 'Hello, I am working!'");
    echo "SUCCESS:\n$result\n";
} catch (Exception $e) {
    echo "ERROR:\n" . $e->getMessage() . "\n";
}
?>
