<?php
/**
 * Twilio Service
 * Handles sending WhatsApp OTPs and Notifications via Twilio API
 */

class TwilioService {
    private $accountSid;
    private $authToken;
    private $twilioWhatsAppNumber;
    
    public function __construct() {
        // Load these from sms_config.php
        $this->accountSid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
        $this->authToken = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
        // E.g., +14155238886
        $this->twilioWhatsAppNumber = defined('TWILIO_WHATSAPP_NUMBER') ? TWILIO_WHATSAPP_NUMBER : ''; 
    }

    public function sendWhatsAppOTP($toPhone, $otpCode, $userName = 'User') {
        // You can customize this message. NOTE: If you are using Twilio in production 
        // to initiate conversations (outbound without 24hr reply window), 
        // you MUST use a pre-approved WhatsApp Template on your Twilio account!
        $message = "Hi $userName, your Veeru OTP for password reset is: *$otpCode*. It is valid for " . OTP_EXPIRY_MINUTES . " minutes.";
        return $this->sendWhatsAppMessage($toPhone, $message);
    }
    
    public function sendWhatsAppNotification($toPhone, $message) {
        return $this->sendWhatsAppMessage($toPhone, $message);
    }

    public function sendWhatsAppNotificationWithMedia($toPhone, $message, $mediaUrl = '') {
        return $this->sendWhatsAppMessage($toPhone, $message, $mediaUrl);
    }

    private function sendWhatsAppMessage($toPhone, $message, $mediaUrl = '') {
        if (empty($this->accountSid) || empty($this->authToken)) {
            error_log("Twilio credentials not configured in sms_config.php.");
            return false;
        }

        // Format phone number to include country code
        $toPhone = $this->formatIndianPhoneNumber($toPhone);

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        
        $data = [
            'From' => 'whatsapp:' . $this->twilioWhatsAppNumber,
            'To'   => 'whatsapp:' . $toPhone,
            'Body' => $message
        ];

        // Attach logo/media if provided
        if (!empty($mediaUrl)) {
            $data['MediaUrl'] = $mediaUrl;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // FIX FOR LOCAL XAMPP SSL ISSUES
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            error_log("Twilio WhatsApp Error ($httpCode): " . $response);
            return false;
        }
    }
    
    private function formatIndianPhoneNumber($phone) {
        // Remove all non-numeric characters
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // If exactly 10 digits, it's an Indian mobile number without country code
        if (strlen($cleanPhone) == 10) {
            return '+91' . $cleanPhone;
        }
        
        // If it's 12 digits and starts with 91
        if (strlen($cleanPhone) == 12 && substr($cleanPhone, 0, 2) == '91') {
            return '+' . $cleanPhone;
        }
        
        // Return standard E.164 formatted string
        return '+' . $cleanPhone;
    }
}
?>
