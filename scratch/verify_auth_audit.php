<?php
/**
 * Verification Script for Independent Student & Teacher Registration & Login Isolation
 * Veeru
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$sharedEmail = 'test_shared_isolate_' . time() . '@example.com';
$mobileTeacher = '98765' . rand(10000, 99999);
$mobileStudent = '98765' . rand(10000, 99999);
$passTeacher = 'TeacherPass123!';
$passStudent = 'StudentPass123!';

$results = [
    'teacher_registration' => false,
    'student_registration_same_email' => false,
    'teacher_login_isolated' => false,
    'student_login_isolated' => false,
    'cleanup' => false
];

try {
    // 1. Create Teacher Account
    $tHash = password_hash($passTeacher, PASSWORD_DEFAULT);
    $tStmt = $pdo->prepare("
        INSERT INTO users (name, email, mobile, password, user_type, subscription_status, school_name, created_at, updated_at)
        VALUES ('Teacher User', ?, ?, ?, 'teacher', 'active', 'Test School', NOW(), NOW())
    ");
    $tStmt->execute([$sharedEmail, $mobileTeacher, $tHash]);
    $teacherId = $pdo->lastInsertId();

    if ($teacherId > 0) {
        $results['teacher_registration'] = true;
    }

    // 2. Create Student Account WITH THE EXACT SAME EMAIL ADDRESS
    $sHash = password_hash($passStudent, PASSWORD_DEFAULT);
    $sStmt = $pdo->prepare("
        INSERT INTO users (name, email, mobile, password, user_type, subscription_status, school_name, class_id, board_type, created_at, updated_at)
        VALUES ('Student User', ?, ?, ?, 'student', 'active', 'Test School', 1, 'CBSE', NOW(), NOW())
    ");
    $sStmt->execute([$sharedEmail, $mobileStudent, $sHash]);
    $studentId = $pdo->lastInsertId();

    if ($studentId > 0) {
        $results['student_registration_same_email'] = true;
    }

    // 3. Test Student Login Isolation
    $stmtS = $pdo->prepare("SELECT user_id, password FROM users WHERE LOWER(email) = LOWER(?) AND user_type = 'student'");
    $stmtS->execute([$sharedEmail]);
    $sData = $stmtS->fetch();
    if ($sData && password_verify($passStudent, $sData['password'])) {
        $results['student_login_isolated'] = true;
    }

    // 4. Test Teacher Login Isolation
    $stmtT = $pdo->prepare("SELECT user_id, password FROM users WHERE LOWER(email) = LOWER(?) AND user_type = 'teacher'");
    $stmtT->execute([$sharedEmail]);
    $tData = $stmtT->fetch();
    if ($tData && password_verify($passTeacher, $tData['password'])) {
        $results['teacher_login_isolated'] = true;
    }

    // 5. Cleanup
    $pdo->prepare("DELETE FROM users WHERE user_id IN (?, ?)")->execute([$teacherId, $studentId]);
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
