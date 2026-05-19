<?php
/**
 * Join Classroom API (Student App)
 * Allows a student to join a specific teacher's classroom using a 6-digit code.
 */
require_once '../../config/db.php';
require_once '../cors_middleware.php';

$data = getJsonInput();

// Required Fields
$missing = validateRequired($data, ['student_id', 'class_code']);
if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$student_id = intval($data['student_id']);
$class_code = strtoupper(sanitizeInput($data['class_code']));

try {
    // 1. Ensure assigned_teacher_id exists in users table
    try {
        $pdo->query("SELECT assigned_teacher_id FROM users LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN assigned_teacher_id INT DEFAULT NULL");
        } catch (PDOException $ex) {
            // Silently ignore if it already exists
        }
    }

    // 2. Ensure division_name exists in users table
    try {
        $pdo->query("SELECT division_name FROM users LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN division_name VARCHAR(50) DEFAULT NULL");
        } catch (PDOException $ex) {
            // Silently ignore if it already exists
        }
    }

    // 2. Look up the class_code in teacher_classes
    $stmt = $pdo->prepare("
        SELECT tc.teacher_id, tc.class_id, tc.division_name, c.class_name, u.school_name, u.name as teacher_name
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.class_id
        JOIN users u ON tc.teacher_id = u.user_id
        WHERE tc.class_code = ?
    ");
    $stmt->execute([$class_code]);
    $classroom = $stmt->fetch();

    if (!$classroom) {
        sendResponse('error', 'Invalid School ID / Class Code. Please check and try again.', null, 404);
    }

    // 3. Update the Student's profile
    // Note: This overrides their previous class/school settings with the official ones from the code.
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET class_id = ?, 
            assigned_teacher_id = ?, 
            division_name = ?, 
            school_name = ?,
            subscription_status = 'active'
        WHERE user_id = ? AND user_type = 'student'
    ");
    
    $updateStmt->execute([
        $classroom['class_id'],
        $classroom['teacher_id'],
        $classroom['division_name'],
        $classroom['school_name'],
        $student_id
    ]);

    if ($updateStmt->rowCount() === 0) {
        // Double check if student exists
        $check = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $check->execute([$student_id]);
        if (!$check->fetch()) {
            sendResponse('error', 'Student account not found.', null, 404);
        }
    }

    sendResponse('success', 'Successfully joined the classroom!', [
        'school_name' => $classroom['school_name'] ?? 'Your School',
        'teacher_name' => $classroom['teacher_name'],
        'class_name' => $classroom['class_name'],
        'division_name' => $classroom['division_name'],
        'class_id' => $classroom['class_id']
    ]);

} catch (PDOException $e) {
    error_log("Join Classroom Error: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
