<?php
/**
 * Email / OTP Configuration
 * Resend API credentials for sending OTP emails
 */

// (Removed Resend credentials)
// Tomorrow: Add Twilio or Interakt credentials here.

// -----------------------------------------------
// Twilio WhatsApp API Credentials
// -----------------------------------------------
// These MUST be set as Environment Variables in Railway dashboard:
//   TWILIO_ACCOUNT_SID = ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
//   TWILIO_AUTH_TOKEN  = your_auth_token
//   TWILIO_WHATSAPP_NUMBER = +14155238886  (Sandbox) or your approved number
// DO NOT hardcode credentials here — GitHub will block the push!
if (!defined('TWILIO_ACCOUNT_SID')) {
    define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: ''); 
}

if (!defined('TWILIO_AUTH_TOKEN')) {
    define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: ''); 
}

if (!defined('TWILIO_WHATSAPP_NUMBER')) {
    define('TWILIO_WHATSAPP_NUMBER', getenv('TWILIO_WHATSAPP_NUMBER') ?: '+14155238886'); 
}

// -----------------------------------------------
// Legacy MSG91 (kept for reference, not used)
// -----------------------------------------------
if (!defined('MSG91_AUTH_KEY')) {
    define('MSG91_AUTH_KEY', '494908AexhxcvQwe1M6994be64P1');
}

if (!defined('MSG91_SENDER_ID')) {
    define('MSG91_SENDER_ID', 'VERUAP'); // Your approved Sender ID (6 chars max)
}

if (!defined('MSG91_ROUTE')) {
    define('MSG91_ROUTE', '4'); // 4 = Transactional Route (for OTP)
}

if (!defined('MSG91_DLT_TEMPLATE_ID')) {
    // Replace with your DLT approved template ID
    define('MSG91_DLT_TEMPLATE_ID', 'YOUR_DLT_TEMPLATE_ID');
}

// OTP Configuration
if (!defined('OTP_EXPIRY_MINUTES')) {
    define('OTP_EXPIRY_MINUTES', 10); // OTP valid for 10 minutes
}

if (!defined('OTP_MAX_ATTEMPTS_PER_HOUR')) {
    define('OTP_MAX_ATTEMPTS_PER_HOUR', 100); // Increased for testing (was 3)
}

if (!defined('OTP_LENGTH')) {
    define('OTP_LENGTH', 6); // 6-digit OTP
}
?>
