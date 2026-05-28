<?php
/**
 * Delete Class API (Teacher)
 * Veeru
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : (isset($data['teacher_id']) ? intval($data['teacher_id']) : 0);
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : (isset($data['class_id']) ? intval($data['class_id']) : 0);

    if ($teacher_id <= 0 || $class_id <= 0) {
        sendResponse('error', 'Invalid teacher_id or class_id', null, 400);
    }

    // Verify teacher owns the class
    $stmt = $pdo->prepare("SELECT * FROM teacher_classes WHERE teacher_id = ? AND class_id = ?");
    $stmt->execute([$teacher_id, $class_id]);
    if (!$stmt->fetch()) {
        sendResponse('error', 'Class not found or unauthorized', null, 403);
    }

    // Start transaction to safely delete related data if necessary, though ON DELETE CASCADE should handle this if foreign keys are set up.
    // For now, we delete from teacher_classes, and possibly classes.
    // Wait, the `classes` table has the class_code. `teacher_classes` links it.
    // We should delete the class from `classes` table which will cascade, or delete manually.
    
    $pdo->beginTransaction();

    // 1. Unassign students from this class (optional, or just let them be orphaned but we should probably set their class_id to NULL)
    $updateStudents = $pdo->prepare("UPDATE users SET class_id = NULL WHERE class_id = ? AND user_type = 'student'");
    $updateStudents->execute([$class_id]);

    // 2. Delete teacher_classes link
    $delTeacherClass = $pdo->prepare("DELETE FROM teacher_classes WHERE class_id = ? AND teacher_id = ?");
    $delTeacherClass->execute([$class_id, $teacher_id]);

    // 3. Delete the class itself
    $delClass = $pdo->prepare("DELETE FROM classes WHERE class_id = ?");
    $delClass->execute([$class_id]);

    $pdo->commit();
    
    sendResponse('success', 'Class deleted successfully', null, 200);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse('error', 'Server error occurred', ['error' => $e->getMessage()], 500);
}
?>
