<?php
/**
 * Google Gemini API Configuration & Helper
 * Optimized for Stability and Speed
 */

// 1. Define API Key (Prevent re-definition errors)
if (!defined('GEMINI_API_KEY')) {
    // Try environment variable first, then fallback to hardcoded
    $envKey = getenv('GEMINI_API_KEY');
    define('GEMINI_API_KEY', $envKey ? $envKey : 'AIzaSyCHHQqiOifU2KUpenX39imsL3qQ99wIFuw');
}

// 2. Define API URL (Corrected Model Name)
if (!defined('GEMINI_API_URL')) {
// Using verified gemini-2.5-flash (Confirmed working)
    define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');
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
                // Default settings if not provided
                'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.7,
                'maxOutputTokens' => isset($options['maxOutputTokens']) ? $options['maxOutputTokens'] : 800
            ]
        ];
        
        $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // TIMEOUT: Prevent hanging forever (30 seconds)
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // SSL FIX: Crucial for XAMPP Localhost
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        // Handle Connection Errors
        if ($curlError) {
            throw new Exception("cURL Connection Error: " . $curlError);
        }
        
        // Handle HTTP Errors (Like 404 Model Not Found or 400 Bad Request)
        if ($httpCode !== 200) {
            $errorDetails = json_decode($response, true);
            $msg = isset($errorDetails['error']['message']) ? $errorDetails['error']['message'] : $response;
            throw new Exception("Gemini API Error (HTTP $httpCode): " . $msg);
        }
        
        // Parse Response
        $decodedResponse = json_decode($response, true);
        
        if (isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])) {
            return $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
        }
        
        throw new Exception("Invalid response format from AI.");
    }
}
?>