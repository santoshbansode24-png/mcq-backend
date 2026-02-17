<?php
/**
 * SMS Configuration for MSG91
 * Store your MSG91 credentials here
 */

if (!defined('MSG91_AUTH_KEY')) {
    define('MSG91_AUTH_KEY', '494908AexhxcvQwe1M6994be64P1'); // MSG91 Auth Key
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
    define('OTP_MAX_ATTEMPTS_PER_HOUR', 3); // Max 3 OTP requests per hour
}

if (!defined('OTP_LENGTH')) {
    define('OTP_LENGTH', 6); // 6-digit OTP
}
?>
