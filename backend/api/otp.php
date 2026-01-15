<?php
// Debug Log - Moved to top
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'UNKNOWN_ACTION';
file_put_contents('../otp_debug.log', date('Y-m-d H:i:s') . " [TOP] Action: $action Input: " . json_encode($input) . "\n", FILE_APPEND);

require_once '../config/db.php';
require_once 'cors_middleware.php';

header('Content-Type: application/json');

if ($action === 'send_otp') {
    $mobile = $input['mobile'] ?? '';

    if (empty($mobile)) {
        echo json_encode(['status' => 'error', 'message' => 'Mobile number required']);
        exit;
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE mobile = ?");
    $stmt->execute([$mobile]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Mobile number not registered']);
        exit;
    }

    // Generate OTP
    $otp = rand(1000, 9999);

    // Save to DB
    // First delete old OTPs for this number
    $pdo->prepare("DELETE FROM otp_store WHERE mobile = ?")->execute([$mobile]);
    
    $stmt = $pdo->prepare("INSERT INTO otp_store (mobile, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    if ($stmt->execute([$mobile, $otp])) {
        
        // --- SIMULATE SENDING SMS ---
        // For production, replace this with Msg91/Fast2SMS API call
        // For now, we allow the app to "know" the OTP for testing if needed, or just rely on the log
        
        // Log it clearly for the user to see
        $logMessage = "--------------------------------------------------\n";
        $logMessage .= "OTP for Mobile $mobile is: $otp\n";
        $logMessage .= "--------------------------------------------------\n";
        file_put_contents('../OTP_SENT.log', $logMessage, FILE_APPEND);

        echo json_encode([
            'status' => 'success', 
            'message' => 'OTP sent successfully',
            'debug_otp' => $otp // REMOVE THIS IN PRODUCTION
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to generate OTP']);
    }

} elseif ($action === 'reset_password') {
    $mobile = $input['mobile'] ?? '';
    $otp = $input['otp'] ?? '';
    $newPassword = $input['new_password'] ?? '';

    if (empty($mobile) || empty($otp) || empty($newPassword)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    // Verify OTP
    $stmt = $pdo->prepare("SELECT * FROM otp_store WHERE mobile = ? AND otp = ? AND expires_at > NOW()");
    $stmt->execute([$mobile, $otp]);
    $otpRecord = $stmt->fetch();

    if ($otpRecord) {
        // OTP Valid. Reset Password.
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE mobile = ?");
        if ($updateStmt->execute([$hashedPassword, $mobile])) {
            
            // Delete used OTP
            $pdo->prepare("DELETE FROM otp_store WHERE mobile = ?")->execute([$mobile]);

            echo json_encode(['status' => 'success', 'message' => 'Password reset successful']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update password']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or Expired OTP']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
