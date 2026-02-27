<?php
/**
 * Send WhatsApp Notification API
 * Allows sending a WhatsApp message to a specific user via Twilio
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';
require_once '../config/sms_config.php';
require_once '../services/TwilioService.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

try {
    // Get JSON input
    $input = getJsonInput();

    if (empty($input['user_id']) || empty($input['message'])) {
        sendResponse('error', 'User ID and Message are required', null, 400);
    }

    $userId = intval($input['user_id']);
    $message = trim($input['message']);

    // Find the user to get their phone number
    $stmt = $pdo->prepare("SELECT user_id, name, mobile, phone FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse('error', 'User not found', null, 404);
    }

    $userPhone = !empty($user['mobile']) ? $user['mobile'] : $user['phone'];

    if (empty($userPhone)) {
        sendResponse('error', 'No phone number associated with this user', null, 400);
    }

    // Send Notification via Twilio WhatsApp
    $twilioService = new TwilioService();
    $whatsappResult = $twilioService->sendWhatsAppNotification($userPhone, $message);

    if (!$whatsappResult) {
        sendResponse('error', 'Failed to send WhatsApp message. Check Twilio configuration or logs.', null, 500);
    }

    // Mask phone for response
    $maskedPhone = substr($userPhone, 0, 3) . '****' . substr($userPhone, -4);

    sendResponse('success', "Notification sent successfully to WhatsApp: " . $maskedPhone, [
        "user_id" => $userId
    ], 200);

} catch (Exception $e) {
    error_log("Send WhatsApp Notification Error: " . $e->getMessage());
    sendResponse('error', 'An error occurred. Please try again later.', ['debug' => $e->getMessage()], 500);
}
?>
