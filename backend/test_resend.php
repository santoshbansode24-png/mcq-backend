<?php
/**
 * Resend Email Test Script
 * Visit: http://localhost/veeru/backend/test_resend.php?email=YOUR_EMAIL
 */

require_once 'config/sms_config.php';
require_once 'services/EmailService.php';

header('Content-Type: application/json');

$testEmail = $_GET['email'] ?? '';

if (empty($testEmail)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Please provide ?email=your@email.com in the URL',
        'config'  => [
            'api_key_set'  => defined('RESEND_API_KEY') && RESEND_API_KEY !== 'YOUR_RESEND_API_KEY',
            'api_key_preview' => defined('RESEND_API_KEY') ? substr(RESEND_API_KEY, 0, 10) . '...' : 'NOT SET',
            'from_email'   => defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : 'NOT SET',
        ]
    ]);
    exit();
}

// Test sending
$emailService = new EmailService();
$result = $emailService->sendOTP($testEmail, '123456', 'Test User');

echo json_encode([
    'status'     => $result['success'] ? 'success' : 'error',
    'message'    => $result['message'],
    'test_email' => $testEmail,
    'from_email' => RESEND_FROM_EMAIL,
    'api_key_preview' => substr(RESEND_API_KEY, 0, 10) . '...',
    'raw_result' => $result,
]);
?>
