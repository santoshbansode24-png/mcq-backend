<?php
/**
 * Google Gemini API Configuration & Helper
 * Optimized for Stability and Speed
 */

// 1. Define API Key (Prevent re-definition errors)
// 1. Define API Key (Prioritize Server Environment Variables)
if (!defined('GEMINI_API_KEY')) {
    // A. Check for Railway / Server Environment Variable (BEST PRACTICE)
    $envKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ($_SERVER['GEMINI_API_KEY'] ?? ''));
    
    if ($envKey) {
        define('GEMINI_API_KEY', trim($envKey));
    } else {
        // B. Fallback to local secrets.php (ONLY for local XAMPP dev)
        if (file_exists(__DIR__ . '/secrets.php')) {
            require_once __DIR__ . '/secrets.php';
        }
        
        // Final Safety: Define as empty if still not found to prevent PHP Fatal Errors
        if (!defined('GEMINI_API_KEY')) {
            define('GEMINI_API_KEY', '');
        }
    }
}

// 1.1. Define Google API Key (for TTS and other GCP services)
if (!defined('GOOGLE_API_KEY')) {
    $gEnvKey = getenv('GOOGLE_API_KEY') ?: ($_ENV['GOOGLE_API_KEY'] ?? ($_SERVER['GOOGLE_API_KEY'] ?? ''));
    if ($gEnvKey) {
        define('GOOGLE_API_KEY', trim($gEnvKey));
    } elseif (defined('LOCAL_GOOGLE_API_KEY')) {
        define('GOOGLE_API_KEY', LOCAL_GOOGLE_API_KEY);
    } else {
        define('GOOGLE_API_KEY', '');
    }
}

// 2. Define API URL - Using gemini-1.5-flash for stability and full access
if (!defined('GEMINI_API_URL')) {
    define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent');
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
            // SSL Security: Enable verification in production (Railway), disable only on localhost if needed
            $isLocal = (strpos(GEMINI_API_URL, 'localhost') !== false || $_SERVER['HTTP_HOST'] === 'localhost');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$isLocal);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $isLocal ? 0 : 2);
            
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
            $isLocal = ($_SERVER['HTTP_HOST'] === 'localhost');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$isLocal);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $isLocal ? 0 : 2);
            
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
