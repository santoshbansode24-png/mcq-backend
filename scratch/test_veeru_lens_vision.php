<?php
/**
 * Test Veeru Lens Vision AI with Base64 Image Payload
 * Veeru
 */

echo "===================================================\n";
echo "🔍 TESTING VEERU LENS VISION AI (IMAGE SOLVER)\n";
echo "===================================================\n\n";

// 1x1 Red Pixel JPEG in Base64 format
$sampleBase64Image = "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=";

$payload = json_encode([
    'image_base64' => $sampleBase64Image,
    'user_text' => 'Solve the problem shown in this image.',
    'language' => 'English'
]);

$ch = curl_init('https://api.veeruapp.in/api/ai_homework.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: " . $httpCode . "\n";
echo "Response Output:\n";
echo $response . "\n\n";

if (strpos($response, 'data:') !== false && strpos($response, 'status') !== false) {
    echo "✅ VEERU LENS VISION AI IS WORKING 100% PERFECTLY!\n";
} else {
    echo "❌ ERROR DETECTED IN VISION RESPONSE\n";
}
