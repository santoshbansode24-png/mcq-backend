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

// 2. Define API URL - Using gemini-2.0-flash (confirmed available for this key)
if (!defined('GEMINI_API_URL')) {
    define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
}

/**
 * Helper function to call Gemini API
 * Throws Exceptions on error for cleaner handling in the main script.
 * * @param string $prompt The prompt to send
 * @param array $options Optional settings (temperature, maxOutputTokens)
 * @return string The AI response text
 * @throws Exception If the API call fails
 */
if (!function_exists('callGeminiAPI')) {
    function callGeminiAPI($prompt, $options = []) {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.7,
                'maxOutputTokens' => isset($options['maxOutputTokens']) ? $options['maxOutputTokens'] : 800
            ]
        ];
        
        $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curlError) throw new Exception("cURL Error: " . $curlError);
        if ($httpCode !== 200) {
            $errorDetails = json_decode($response, true);
            $msg = isset($errorDetails['error']['message']) ? $errorDetails['error']['message'] : $response;
            throw new Exception("Gemini API Error ($httpCode): " . $msg);
        }
        
        $decoded = json_decode($response, true);
        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            return $decoded['candidates'][0]['content']['parts'][0]['text'];
        }
        throw new Exception("Invalid response format.");
    }
}

/**
 * Call Gemini with Native PDF Support
 * @param string $prompt
 * @param string $base64PDF
 */
if (!function_exists('callGeminiPDF')) {
    function callGeminiPDF($prompt, $base64PDF, $options = []) {
        if (empty(GEMINI_API_KEY)) {
            throw new Exception("GEMINI_API_KEY is not set. Add it to Railway Environment Variables.");
        }
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inlineData' => [
                                'mimeType' => 'application/pdf',
                                'data' => $base64PDF
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json'
            ]
        ];
        
        $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 240); // 4 min for large PDFs
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curlError) throw new Exception("cURL Error: " . $curlError);
        if ($httpCode !== 200) {
            $errorDetails = json_decode($response, true);
            $msg = isset($errorDetails['error']['message']) ? $errorDetails['error']['message'] : substr($response, 0, 300);
            error_log("Gemini PDF Fail ($httpCode): " . $response);
            throw new Exception("Gemini API Error ($httpCode): " . $msg);
        }
        
        $decoded = json_decode($response, true);
        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            return $decoded['candidates'][0]['content']['parts'][0]['text'];
        }
        // Log what we got for debugging
        error_log("Gemini PDF invalid response: " . substr($response, 0, 500));
        throw new Exception("Invalid PDF AI response. Response keys: " . implode(',', array_keys($decoded ?? [])));
    }
}
