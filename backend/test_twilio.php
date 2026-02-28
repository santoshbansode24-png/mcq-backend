<?php
/**
 * Twilio WhatsApp Test Script
 * Run this on Railway to debug Twilio OTP sending
 * URL: https://api.veeruapp.in/backend/test_twilio.php?phone=7755952198
 * 
 * DELETE THIS FILE after testing is complete!
 */

require_once 'config/sms_config.php';
require_once 'services/TwilioService.php';

header('Content-Type: application/json');

$results = [];

// 1. Check credentials
$results[] = [
    'check' => 'Twilio Account SID',
    'value' => substr(TWILIO_ACCOUNT_SID, 0, 8) . '...',
    'status' => strlen(TWILIO_ACCOUNT_SID) > 10 ? '✅ Set' : '❌ Empty'
];
$results[] = [
    'check' => 'Twilio Auth Token',
    'value' => substr(TWILIO_AUTH_TOKEN, 0, 4) . '...',
    'status' => strlen(TWILIO_AUTH_TOKEN) > 10 ? '✅ Set' : '❌ Empty'
];
$results[] = [
    'check' => 'Twilio WhatsApp Number',
    'value' => TWILIO_WHATSAPP_NUMBER,
    'status' => !empty(TWILIO_WHATSAPP_NUMBER) ? '✅ Set' : '❌ Empty'
];

// 2. Test actual send (use ?phone= query param)
$testPhone = $_GET['phone'] ?? '';

if (empty($testPhone)) {
    $results[] = ['check' => 'Test Send', 'status' => '⏭️ Skipped - pass ?phone=XXXXXXXXXX to test actual send'];
} else {
    $testOtp = rand(100000, 999999);
    $results[] = ['check' => 'Test Phone', 'value' => $testPhone, 'test_otp' => $testOtp];
    
    $twilioService = new TwilioService();
    $sent = $twilioService->sendWhatsAppOTP($testPhone, $testOtp, 'Test User');
    
    $results[] = [
        'check' => 'WhatsApp Send Result',
        'status' => $sent ? '✅ Sent successfully!' : '❌ Failed - check Railway logs for Twilio error'
    ];
}

// 3. Check if env vars are being used (Railway should override hardcoded values)
$results[] = [
    'check' => 'Environment Source',
    'value' => getenv('TWILIO_ACCOUNT_SID') ? 'From Railway ENV vars' : 'From sms_config.php hardcoded fallback',
    'status' => '📋 Info'
];

echo json_encode([
    'status' => 'ok',
    'instructions' => [
        '1. Open WhatsApp and message +14155238886',
        '2. Send the message: join <your-sandbox-keyword>',
        '3. You should receive a confirmation from Twilio',
        '4. Then run this URL with your phone: https://api.veeruapp.in/backend/test_twilio.php?phone=7755952198'
    ],
    'results' => $results
], JSON_PRETTY_PRINT);
?>
