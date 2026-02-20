<?php
// backend/api/test_resend.php
require_once '../services/EmailService.php';

header('Content-Type: application/json');

$testEmail = isset($_GET['email']) ? $_GET['email'] : 'santoshbansode24@gmail.com';
$testName = "Veeru Test User";
$testOTP = "123456";

echo "Testing Resend Email Integration...\n";
echo "Sending to: $testEmail\n";

$emailService = new EmailService();
$result = $emailService->sendOTP($testEmail, $testOTP, $testName);

if ($result['success']) {
    echo "\n✅ SUCCESS: Email sent successfully!\n";
    echo "Message: " . $result['message'] . "\n";
} else {
    echo "\n❌ FAILURE: Email delivery failed.\n";
    echo "Message: " . $result['message'] . "\n";
    if (isset($result['error'])) {
        echo "Error Details: " . json_encode($result['error'], JSON_PRETTY_PRINT) . "\n";
    }
}
?>
