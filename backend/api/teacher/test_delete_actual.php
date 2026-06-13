<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../cors_middleware.php';

$teacher_id = 45;
$class_id = 19;

echo "Diagnostic dry-run deletion for Teacher $teacher_id, Class $class_id:\n\n";

try {
    // Verify teacher owns the classroom
    $stmt = $pdo->prepare("SELECT class_code FROM classrooms WHERE teacher_id = ? AND class_id = ?");
    $stmt->execute([$teacher_id, $class_id]);
    $classroom = $stmt->fetch();
    
    if (!$classroom) {
        die("Class not found or unauthorized\n");
    }
    
    $class_code = $classroom['class_code'];
    echo "Found classroom with code: $class_code\n";

    echo "Starting transaction...\n";
    $pdo->beginTransaction();

    // 1. Unassign students (delete mappings)
    echo "1. Deleting student_class_mapping...\n";
    $delMapping = $pdo->prepare("DELETE FROM student_class_mapping WHERE class_id = ?");
    $delMapping->execute([$class_id]);
    echo "Done. Rows affected: " . $delMapping->rowCount() . "\n";

    // 2. Delete teacher_classes link
    echo "2. Deleting teacher_classes...\n";
    $delTeacherClass = $pdo->prepare("DELETE FROM teacher_classes WHERE class_code = ? AND teacher_id = ?");
    $delTeacherClass->execute([$class_code, $teacher_id]);
    echo "Done. Rows affected: " . $delTeacherClass->rowCount() . "\n";

    // 3. Delete the classroom itself
    echo "3. Deleting classroom...\n";
    $delClassroom = $pdo->prepare("DELETE FROM classrooms WHERE class_id = ?");
    $delClassroom->execute([$class_id]);
    echo "Done. Rows affected: " . $delClassroom->rowCount() . "\n";

    // 4. Delete associated live exams, homework, notifications
    echo "4. Deleting associated records (live_exams, class_updates, notifications)...\n";
    $d1 = $pdo->prepare("DELETE FROM live_exams WHERE class_id = ?");
    $d1->execute([$class_id]);
    echo "Deleted live_exams. Rows affected: " . $d1->rowCount() . "\n";
    
    $d2 = $pdo->prepare("DELETE FROM class_updates WHERE class_id = ?");
    $d2->execute([$class_id]);
    echo "Deleted class_updates. Rows affected: " . $d2->rowCount() . "\n";
    
    $d3 = $pdo->prepare("DELETE FROM notifications WHERE class_id = ?");
    $d3->execute([$class_id]);
    echo "Deleted notifications. Rows affected: " . $d3->rowCount() . "\n";

    echo "\nRolling back transaction safely...\n";
    $pdo->rollBack();
    echo "SUCCESS: Dry-run completed cleanly without errors!\n";
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "Transaction rolled back due to error.\n";
    }
    echo "ERROR OCCURRED:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?>
