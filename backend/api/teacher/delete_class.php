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

    // Verify teacher owns the classroom
    $stmt = $pdo->prepare("SELECT class_code FROM classrooms WHERE teacher_id = ? AND class_id = ?");
    $stmt->execute([$teacher_id, $class_id]);
    $classroom = $stmt->fetch();
    
    if (!$classroom) {
        sendResponse('error', 'Class not found or unauthorized', null, 403);
    }
    
    $class_code = $classroom['class_code'];

    $pdo->beginTransaction();

    // 1. Unassign students (delete mappings)
    $delMapping = $pdo->prepare("DELETE FROM student_class_mapping WHERE class_id = ?");
    $delMapping->execute([$class_id]);

    // 2. Delete teacher_classes link via class_code
    $delTeacherClass = $pdo->prepare("DELETE FROM teacher_classes WHERE class_code = ? AND teacher_id = ?");
    $delTeacherClass->execute([$class_code, $teacher_id]);

    // 3. Delete the classroom itself
    $delClassroom = $pdo->prepare("DELETE FROM classrooms WHERE class_id = ?");
    $delClassroom->execute([$class_id]);

    // 4. Delete associated live exams, homework, notifications
    $pdo->prepare("DELETE FROM live_exams WHERE class_id = ?")->execute([$class_id]);
    $pdo->prepare("DELETE FROM class_updates WHERE class_id = ?")->execute([$class_id]);
    $pdo->prepare("DELETE FROM notifications WHERE class_id = ?")->execute([$class_id]);

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
