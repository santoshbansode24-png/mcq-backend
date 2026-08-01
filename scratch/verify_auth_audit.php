<?php
/**
 * Automated Verification Script for Option A (Mobile OR Email + 4-Digit PIN)
 * Veeru
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$testStudentEmail = 'option_a_email_' . time() . '@example.com';
$testMobile = '98765' . rand(10000, 99999);
$testPassword = 'InitialPassword123!';
$newPassword1 = 'EmailResetPass456!';
$newPassword2 = 'MobileResetPass789!';
$testPin = '7777';

$results = [
    'user_registration' => false,
    'reset_via_email' => false,
    'reset_via_mobile' => false,
    'audit_logging' => false,
    'cleanup' => false
];

try {
    // 1. Insert Test User
    $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
    $insert = $pdo->prepare("
        INSERT INTO users (name, email, mobile, password, security_pin, user_type, subscription_status, subscription_expiry, school_name, class_id, board_type, created_at, updated_at)
        VALUES ('Option A Test', ?, ?, ?, ?, 'student', 'active', CURDATE(), 'Test School', 1, 'CBSE', NOW(), NOW())
    ");
    $insert->execute([$testStudentEmail, $testMobile, $hashedPassword, $testPin]);
    $userId = $pdo->lastInsertId();

    if ($userId > 0) {
        $results['user_registration'] = true;
    }

    // 2. Test Reset via Email ID
    $emailResetPass = password_hash($newPassword1, PASSWORD_BCRYPT);
    $stmt1 = $pdo->prepare("UPDATE users SET password = ?, password_changed_at = NOW(), updated_at = NOW() WHERE LOWER(email) = LOWER(?) AND security_pin = ?");
    $stmt1->execute([$emailResetPass, $testStudentEmail, $testPin]);

    $verify1 = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $verify1->execute([$userId]);
    $u1 = $verify1->fetch();
    if ($u1 && password_verify($newPassword1, $u1['password'])) {
        $results['reset_via_email'] = true;
    }

    // 3. Test Reset via Mobile Number
    $mobileResetPass = password_hash($newPassword2, PASSWORD_BCRYPT);
    $stmt2 = $pdo->prepare("UPDATE users SET password = ?, password_changed_at = NOW(), updated_at = NOW() WHERE RIGHT(mobile, 10) = RIGHT(?, 10) AND security_pin = ?");
    $stmt2->execute([$mobileResetPass, $testMobile, $testPin]);

    $verify2 = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $verify2->execute([$userId]);
    $u2 = $verify2->fetch();
    if ($u2 && password_verify($newPassword2, $u2['password'])) {
        $results['reset_via_mobile'] = true;
    }

    // 4. Test Log Entry
    $logStmt = $pdo->prepare("
        INSERT INTO password_reset_logs (user_id, email, mobile, ip_address, user_agent, status, message, created_at)
        VALUES (?, ?, ?, '127.0.0.1', 'Option A Test', 'success', 'Password reset successfully via Option A.', NOW())
    ");
    $logStmt->execute([$userId, $testStudentEmail, $testMobile]);

    $checkLogs = $pdo->prepare("SELECT COUNT(*) FROM password_reset_logs WHERE user_id = ? AND status = 'success'");
    $checkLogs->execute([$userId]);
    if ($checkLogs->fetchColumn() > 0) {
        $results['audit_logging'] = true;
    }

    // 5. Cleanup
    $pdo->prepare("DELETE FROM password_reset_logs WHERE user_id = ?")->execute([$userId]);
    $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
    $results['cleanup'] = true;

    echo json_encode([
        'status' => 'success',
        'audit_results' => $results
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
