<?php
$rawInput = json_encode([
    'image_base64' => 'data:image/jpeg;base64,ABCDEF',
    'user_text' => 'Solve this problem',
    'language' => 'English'
]);

$decoded = json_decode($rawInput, true);

$file = null;
$userText = $_POST['user_text'] ?? ($_POST['text'] ?? ($_POST['question'] ?? ($decoded['user_text'] ?? ($decoded['text'] ?? ($decoded['question'] ?? ($decoded['prompt'] ?? ''))))));
$imageBase64 = $_POST['image_base64'] ?? ($_POST['image'] ?? ($decoded['image_base64'] ?? ($decoded['image'] ?? ($decoded['imageData'] ?? null))));

echo "Parsed userText: " . $userText . "\n";
echo "Parsed imageBase64: " . substr($imageBase64, 0, 30) . "\n";
if (!$file && empty($userText) && empty($imageBase64)) {
    echo "FAILED PARSING!\n";
} else {
    echo "PARSED SUCCESSFULLY!\n";
}
