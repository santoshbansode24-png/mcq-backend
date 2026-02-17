<?php
/**
 * SMS Service Class for MSG91
 * Handles sending OTP via SMS
 */

require_once __DIR__ . '/../config/sms_config.php';

class SMSService {
    
    private $authKey;
    private $senderId;
    private $route;
    private $dltTemplateId;
    
    public function __construct() {
        $this->authKey = MSG91_AUTH_KEY;
        $this->senderId = MSG91_SENDER_ID;
        $this->route = MSG91_ROUTE;
        $this->dltTemplateId = MSG91_DLT_TEMPLATE_ID;
    }
    
    /**
     * Send OTP via SMS using MSG91
     * 
     * @param string $phoneNumber Phone number with country code (e.g., +919876543210)
     * @param string $otp The OTP code to send
     * @return array Result with status and message
     */
    public function sendOTP($phoneNumber, $otp) {
        try {
            // Remove + from phone number if present
            $phoneNumber = str_replace('+', '', $phoneNumber);
            
            // Validate phone number (Indian format)
            if (!preg_match('/^91[6-9]\d{9}$/', $phoneNumber)) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format. Use +91XXXXXXXXXX'
                ];
            }
            
            // Prepare message
            $message = "Your Veeru password reset OTP is $otp. Valid for " . OTP_EXPIRY_MINUTES . " minutes. Do not share this code with anyone.";
            
            // MSG91 API endpoint
            $url = "https://api.msg91.com/api/v5/flow/";
            
            // Prepare payload for MSG91 Flow API
            $postData = [
                'template_id' => $this->dltTemplateId,
                'sender' => $this->senderId,
                'short_url' => '0',
                'mobiles' => $phoneNumber,
                'var' => $otp // Variable for template
            ];
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'authkey: ' . $this->authKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Handle cURL errors
            if ($curlError) {
                error_log("SMS cURL Error: " . $curlError);
                return [
                    'success' => false,
                    'message' => 'Failed to connect to SMS service'
                ];
            }
            
            // Parse response
            $responseData = json_decode($response, true);
            
            // Check if successful
            if ($httpCode == 200 && isset($responseData['type']) && $responseData['type'] == 'success') {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'response' => $responseData
                ];
            } else {
                error_log("SMS API Error: " . $response);
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP. Please try again.',
                    'error' => $responseData
                ];
            }
            
        } catch (Exception $e) {
            error_log("SMS Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while sending OTP'
            ];
        }
    }
    
    /**
     * Generate random OTP code
     * 
     * @param int $length Length of OTP (default 6)
     * @return string Generated OTP
     */
    public static function generateOTP($length = 6) {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= rand(0, 9);
        }
        return $otp;
    }
    
    /**
     * Validate phone number format
     * 
     * @param string $phoneNumber Phone number to validate
     * @return bool True if valid, false otherwise
     */
    public static function validatePhoneNumber($phoneNumber) {
        // Remove spaces and special characters
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // Check if it matches Indian phone number format
        // Accepts: +919876543210 or 919876543210 or 9876543210
        if (preg_match('/^(\+91|91)?[6-9]\d{9}$/', $phoneNumber)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Format phone number to standard format (+91XXXXXXXXXX)
     * 
     * @param string $phoneNumber Phone number to format
     * @return string Formatted phone number
     */
    public static function formatPhoneNumber($phoneNumber) {
        // Remove all non-numeric characters except +
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // Remove + if present
        $phoneNumber = str_replace('+', '', $phoneNumber);
        
        // Add 91 if not present
        if (strlen($phoneNumber) == 10) {
            $phoneNumber = '91' . $phoneNumber;
        }
        
        // Add + prefix
        return '+' . $phoneNumber;
    }
}
?>
