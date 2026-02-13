<?php
/**
 * Test Vocab API Endpoint
 * Simulates the exact API call the student app makes
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTING VOCAB API ENDPOINT ===\n\n";

// Test the actual API endpoint
$userId = 1;
$setNumber = 1;

$url = "http://localhost/veeru/backend/api/vocab_get_set.php?user_id=$userId&set_number=$setNumber";

echo "Testing URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response Length: " . strlen($response) . " bytes\n\n";

if ($httpCode == 200) {
    echo "✓ API endpoint is accessible!\n\n";
    
    $data = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ Response is valid JSON\n\n";
        
        if (isset($data['status']) && $data['status'] === 'success') {
            echo "✓ API returned success status\n\n";
            
            echo "=== Response Data ===\n";
            echo "Set Number: " . ($data['data']['set_number'] ?? 'N/A') . "\n";
            echo "Total Words: " . ($data['data']['total_words'] ?? 'N/A') . "\n";
            echo "Words Count: " . (isset($data['data']['words']) ? count($data['data']['words']) : 0) . "\n\n";
            
            if (isset($data['data']['words']) && count($data['data']['words']) > 0) {
                echo "✓ Words are being returned!\n\n";
                echo "First word sample:\n";
                $firstWord = $data['data']['words'][0];
                echo "  Word: " . ($firstWord['word'] ?? 'N/A') . "\n";
                echo "  Definition: " . ($firstWord['definition'] ?? 'N/A') . "\n";
                echo "  Options: " . (isset($firstWord['options']) ? json_encode($firstWord['options']) : 'N/A') . "\n";
            } else {
                echo "❌ ERROR: No words in response!\n";
            }
        } else {
            echo "❌ ERROR: API returned error status\n";
            echo "Message: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ ERROR: Response is not valid JSON\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
        echo "Raw Response:\n";
        echo $response . "\n";
    }
} else {
    echo "❌ ERROR: API endpoint returned HTTP $httpCode\n";
    echo "Raw Response:\n";
    echo $response . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
