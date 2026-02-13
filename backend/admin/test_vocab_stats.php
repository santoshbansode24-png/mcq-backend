<?php
/**
 * Test Vocab Stats API
 * Tests if the vocab stats endpoint returns data
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTING VOCAB STATS API ===\n\n";

$userId = 1;
$url = "http://localhost/veeru/backend/api/vocab_get_stats.php?user_id=$userId";

echo "Testing URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response:\n";
echo $response . "\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['status'])) {
        echo "Status: " . $data['status'] . "\n";
        if ($data['status'] === 'success' && isset($data['data'])) {
            echo "\nStats Data:\n";
            echo "  Current Set: " . ($data['data']['current_set'] ?? 'N/A') . "\n";
            echo "  Sets Completed: " . ($data['data']['sets_completed'] ?? 'N/A') . "\n";
            echo "  Highest Set Unlocked: " . ($data['data']['highest_set_unlocked'] ?? 'N/A') . "\n";
            echo "  Mastered Words: " . ($data['data']['mastered_words'] ?? 'N/A') . "\n";
        }
    }
}

echo "\n=== TEST COMPLETE ===\n";
?>
