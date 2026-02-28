<?php
// Quick version check - deployed at: 2026-02-28 23:38
header('Content-Type: application/json');
echo json_encode([
    'deployed_at' => '2026-02-28 23:38 IST',
    'version' => 'v2.1',
    'send_otp_file_exists' => file_exists(__DIR__ . '/send_otp.php'),
    'send_otp_first_line' => file_exists(__DIR__ . '/send_otp.php') ? substr(file_get_contents(__DIR__ . '/send_otp.php'), 0, 100) : 'NOT FOUND',
    'twilio_sid_set' => !empty(getenv('TWILIO_ACCOUNT_SID')),
    'twilio_token_set' => !empty(getenv('TWILIO_AUTH_TOKEN')),
    'twilio_number' => getenv('TWILIO_WHATSAPP_NUMBER') ?: 'NOT SET',
]);
