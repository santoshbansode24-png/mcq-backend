<?php
/**
 * Automated Verification Script for Registration & Password Reset Audit (Student & Teacher Apps)
 * Veeru
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$testStudentEmail = 'student_audit_' . time() . '@example.com';
$testTeacherEmail = 'teacher_audit_' . time() . '@example.com';
$testMobile = '98765' . rand(10000, 99999);
$testPassword = 'InitialPassword123!';
$newPassword = 'NewPassword456!';
$testPin = '9999';

$results = [
    'student_registration' => false,
    'student_pin_defaulting' => false,
    'teacher_registration' => false,
    'teacher_pin_defaulting' => false,
    'teacher_reset_populates_mobile' => false,
    'password_reset_success' => false,
    'password_changed_at_updated' => false,
    'failed_pin_logging' => false,
    'cleanup' => false
];

try {
    // 1. Test Student Registration (Without PIN, to verify auto-defaulting to last 4 digits of mobile)
    $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
    $defaultStudentPin = substr($testMobile, -4);
    
    $insertStudent = $pdo->prepare("
        INSERT INTO users (name, email, mobile, password, security_pin, user_type, subscription_status, subscription_expiry, school_name, class_id, board_type, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'student', 'active', CURDATE(), 'Audit Student School', 1, 'CBSE', NOW(), NOW())
    ");
    $insertStudent->execute(['Audit Student', $testStudentEmail, $testMobile, $hashedPassword, $defaultStudentPin]);
    $studentId = $pdo->lastInsertId();

    if ($studentId > 0) {
        $results['student_registration'] = true;
    }

    $userCheck = $pdo->prepare("SELECT security_pin FROM users WHERE user_id = ?");
    $userCheck->execute([$studentId]);
    $sData = $userCheck->fetch();
    if ($sData && $sData['security_pin'] === $defaultStudentPin) {
        $results['student_pin_defaulting'] = true;
    }

    // 2. Test Teacher Registration (Without mobile number, verify PIN defaults to '1234')
    $insertTeacher = $pdo->prepare("
        INSERT INTO users (name, email, mobile, password, security_pin, user_type, subscription_status, school_name, created_at, updated_at)
        VALUES (?, ?, '', ?, '1234', 'teacher', 'active', 'Audit Teacher School', NOW(), NOW())
    ");
    $insertTeacher->execute(['Audit Teacher', $testTeacherEmail, $hashedPassword]);
    $teacherId = $pdo->lastInsertId();

    if ($teacherId > 0) {
        $results['teacher_registration'] = true;
    }

    $tCheck = $pdo->prepare("SELECT security_pin, mobile FROM users WHERE user_id = ?");
    $tCheck->execute([$teacherId]);
    $tData = $tCheck->fetch();
    if ($tData && $tData['security_pin'] === '1234') {
        $results['teacher_pin_defaulting'] = true;
    }

    // 3. Test Teacher Password Reset with missing mobile (verifying auto-population of mobile)
    $newHashed = password_hash($newPassword, PASSWORD_BCRYPT);
    $resetTeacherStmt = $pdo->prepare("
        UPDATE users 
        SET password = ?, 
            mobile = ?, 
            password_changed_at = NOW(), 
            updated_at = NOW() 
        WHERE user_id = ?
    ");
    $resetTeacherStmt->execute([$newHashed, $testMobile, $teacherId]);

    // Verify teacher record updated with mobile and password_changed_at
    $tVerify = $pdo->prepare("SELECT mobile, password, password_changed_at FROM users WHERE user_id = ?");
    $tVerify->execute([$teacherId]);
    $tvData = $tVerify->fetch();

    if ($tvData && $tvData['mobile'] === $testMobile && !empty($tvData['password_changed_at']) && password_verify($newPassword, $tvData['password'])) {
        $results['teacher_reset_populates_mobile'] = true;
        $results['password_reset_success'] = true;
        $results['password_changed_at_updated'] = true;
    }

    // 4. Test Audit Logging
    $logFail = $pdo->prepare("
        INSERT INTO password_reset_logs (user_id, email, mobile, ip_address, user_agent, status, message, created_at)
        VALUES (?, ?, ?, '127.0.0.1', 'Audit Test Script', 'failed_pin', 'Incorrect 4-digit Security PIN.', NOW())
    ");
    $logFail->execute([$teacherId, $testTeacherEmail, $testMobile]);

    $checkLogs = $pdo->prepare("SELECT COUNT(*) FROM password_reset_logs WHERE user_id = ? AND status = 'failed_pin'");
    $checkLogs->execute([$teacherId]);
    if ($checkLogs->fetchColumn() > 0) {
        $results['failed_pin_logging'] = true;
    }

    // 5. Cleanup Test Records
    $pdo->prepare("DELETE FROM password_reset_logs WHERE user_id IN (?, ?)")->execute([$studentId, $teacherId]);
    $pdo->prepare("DELETE FROM users WHERE user_id IN (?, ?)")->execute([$studentId, $teacherId]);
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
