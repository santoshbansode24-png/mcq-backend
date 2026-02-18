<?php
/**
 * Email Service Class using Resend API
 * Handles sending OTP emails for password reset
 */

require_once __DIR__ . '/../config/sms_config.php';

class EmailService {

    private $apiKey;
    private $fromEmail;

    public function __construct() {
        $this->apiKey    = RESEND_API_KEY;
        $this->fromEmail = RESEND_FROM_EMAIL;
    }

    /**
     * Send OTP via Email using Resend API
     *
     * @param string $toEmail  Recipient email address
     * @param string $otp      The OTP code to send
     * @param string $userName Recipient's name (optional)
     * @return array Result with 'success' (bool) and 'message' (string)
     */
    public function sendOTP($toEmail, $otp, $userName = 'Student') {
        try {
            $subject = "Your Veeru Password Reset OTP";

            $htmlBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
                    .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
                    .header { background: #4f46e5; padding: 32px 24px; text-align: center; }
                    .header h1 { color: #fff; margin: 0; font-size: 24px; }
                    .header p { color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px; }
                    .body { padding: 32px 24px; }
                    .otp-box { background: #f0f0ff; border: 2px dashed #4f46e5; border-radius: 12px; text-align: center; padding: 24px; margin: 24px 0; }
                    .otp-code { font-size: 40px; font-weight: bold; color: #4f46e5; letter-spacing: 8px; }
                    .note { color: #6b7280; font-size: 13px; margin-top: 8px; }
                    .footer { background: #f9fafb; padding: 16px 24px; text-align: center; color: #9ca3af; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🎓 Veeru</h1>
                        <p>Password Reset Request</p>
                    </div>
                    <div class='body'>
                        <p>Hi <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                        <p>We received a request to reset your Veeru account password. Use the OTP below to proceed:</p>
                        <div class='otp-box'>
                            <div class='otp-code'>" . htmlspecialchars($otp) . "</div>
                            <div class='note'>Valid for " . OTP_EXPIRY_MINUTES . " minutes only</div>
                        </div>
                        <p style='color:#6b7280; font-size:14px;'>If you did not request this, please ignore this email. Your account is safe.</p>
                        <p style='color:#6b7280; font-size:14px;'><strong>Do not share this OTP with anyone.</strong></p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " Veeru Learning App. All rights reserved.
                    </div>
                </div>
            </body>
            </html>";

            $payload = [
                'from'    => 'Veeru App <' . $this->fromEmail . '>',
                'to'      => [$toEmail],
                'subject' => $subject,
                'html'    => $htmlBody,
            ];

            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("Email cURL Error: " . $curlError);
                return ['success' => false, 'message' => 'Failed to connect to email service'];
            }

            $responseData = json_decode($response, true);

            if ($httpCode === 200 || $httpCode === 201) {
                return ['success' => true, 'message' => 'OTP email sent successfully'];
            } else {
                error_log("Resend API Error ($httpCode): " . $response);
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP email. Please try again.',
                    'error'   => $responseData,
                ];
            }

        } catch (Exception $e) {
            error_log("EmailService Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while sending OTP email'];
        }
    }

    /**
     * Generate a random numeric OTP
     *
     * @param int $length
     * @return string
     */
    public static function generateOTP($length = 6) {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= rand(0, 9);
        }
        return $otp;
    }

    /**
     * Validate email address format
     *
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
?>
