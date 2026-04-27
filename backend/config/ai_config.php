<?php
/**
 * Google Gemini API Configuration & Helper
 * Optimized for Stability and Speed
 */

// 1. Define API Key (Prevent re-definition errors)
if (!defined('GEMINI_API_KEY')) {
    // 1. Try loading from secrets.php (local dev - ignored by Git)
    if (file_exists(__DIR__ . '/secrets.php')) {
        require_once __DIR__ . '/secrets.php';
    }

    // 2. Try Railway / server environment variable
    if (!defined('GEMINI_API_KEY')) {
        $envKey = getenv('GEMINI_API_KEY');
        if (!$envKey) $envKey = $_ENV['GEMINI_API_KEY'] ?? '';
        if (!$envKey) $envKey = $_SERVER['GEMINI_API_KEY'] ?? '';
        // DO NOT remove this - define a safe empty string fallback to prevent fatal PHP errors
        define('GEMINI_API_KEY', $envKey ?: '');
    }
}

// 2. Define API URL - Using gemini-2.0-flash as per user preference
if (!defined('GEMINI_API_URL')) {
    define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
}

/**
 * Helper function to call Gemini API
 * Throws Exceptions on error for cleaner handling in the main script.
 */
if (!function_exists('callGeminiAPI')) {
    function callGeminiAPI($prompt, $options = []) {
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['maxOutputTokens'] ?? 1024
            ]
        ];
        
        $maxRetries = 3;
        $retryDelay = 5; // Start with 5s delay for 429s

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 429 && $attempt < $maxRetries) {
                sleep($retryDelay);
                $retryDelay *= 2; 
                continue;
            }
            
            if ($httpCode !== 200) {
                if ($httpCode === 429) {
                    throw new Exception("AI BUSY (429): Free tier limit reached. Upgrade to Paid Tier in AI Studio for permanent solution.");
                }
                $errorDetails = json_decode($response, true);
                $msg = $errorDetails['error']['message'] ?? "API Error $httpCode";
                throw new Exception("Gemini API Error: " . $msg);
            }
            break;
        }
        
        $decoded = json_decode($response, true);
        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? throw new Exception("Invalid response format.");
    }
}

/**
 * Call Gemini with Native PDF Support
 */
if (!function_exists('callGeminiPDF')) {
    define('WORKER_SECRET', 'veeru_ai_worker_v2_secure_ping');

    function callGeminiPDF($prompt, $base64PDF, $options = []) {
        if (empty(GEMINI_API_KEY)) throw new Exception("GEMINI_API_KEY missing.");

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt], ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $base64PDF]]]]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 65536,
                'responseMimeType' => 'application/json'
            ]
        ];
        
        $maxRetries = 3;
        $retryDelay = 10; // 10s backoff for heavy PDF tasks

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 min
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 429 && $attempt < $maxRetries) {
                sleep($retryDelay);
                $retryDelay *= 2;
                continue;
            }
            
            if ($httpCode !== 200) {
                if ($httpCode === 429) {
                    throw new Exception("VEERU LENS BUSY (429): Quota exhausted. Please upgrade to Paid Tier in Google AI Studio to handle large PDFs.");
                }
                $errorDetails = json_decode($response, true);
                $msg = $errorDetails['error']['message'] ?? "Unknown Error";
                throw new Exception("Gemini PDF Error ($httpCode): $msg");
            }
            break;
        }
        
        $decoded = json_decode($response, true);
        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? throw new Exception("Invalid PDF AI response.");
    }
}
