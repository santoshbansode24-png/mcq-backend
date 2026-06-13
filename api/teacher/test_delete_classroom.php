<?php
/**
 * Diagnostic Classroom Deletion test
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../cors_middleware.php';

echo "Database connection check: ";
if ($pdo) {
    echo "OK\n\n";
} else {
    echo "FAILED\n\n";
    exit();
}

try {
    // 1. Create a dummy classroom to test deletion
    echo "Creating dummy classroom...\n";
    $test_teacher_id = 1; // Assuming teacher 1 exists
    $test_class_code = 'TEST99';
    
    // Clean up any old test classroom first
    $pdo->prepare("DELETE FROM classrooms WHERE class_code = ?")->execute([$test_class_code]);
    $pdo->prepare("DELETE FROM teacher_classes WHERE class_code = ?")->execute([$test_class_code]);
    
    // Insert classroom
    $stmt = $pdo->prepare("INSERT INTO classrooms (teacher_id, class_code, class_name, board, medium, class_level) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$test_teacher_id, $test_class_code, 'Test Temp Class', 'CBSE', 'English', 10]);
    $class_id = $pdo->lastInsertId();
    echo "Created classroom with ID: $class_id\n";
    
    // Insert a dummy teacher_class record
    $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, division_name, class_code) VALUES (?, ?, ?, ?)");
    $stmt->execute([$test_teacher_id, $class_id, 'Temp Div', $test_class_code]);
    echo "Created teacher_classes record\n";

    // Insert dummy mapping
    $stmt = $pdo->prepare("INSERT INTO student_class_mapping (student_id, class_id) VALUES (?, ?)");
    $user = $pdo->query("SELECT user_id FROM users LIMIT 1")->fetch();
    if ($user) {
        $stmt->execute([$user['user_id'], $class_id]);
        echo "Created student_class_mapping record\n";
    }

    echo "\nStarting Deletion Transaction...\n";
    $pdo->beginTransaction();

    // Step 1: Mappings
    echo "Step 1: Deleting mappings...\n";
    $delMapping = $pdo->prepare("DELETE FROM student_class_mapping WHERE class_id = ?");
    $delMapping->execute([$class_id]);
    echo "Step 1 complete. Rows affected: " . $delMapping->rowCount() . "\n";

    // Step 2: Teacher classes
    echo "Step 2: Deleting teacher_classes...\n";
    $delTeacherClass = $pdo->prepare("DELETE FROM teacher_classes WHERE class_code = ? AND teacher_id = ?");
    $delTeacherClass->execute([$test_class_code, $test_teacher_id]);
    echo "Step 2 complete. Rows affected: " . $delTeacherClass->rowCount() . "\n";

    // Step 3: Classroom itself
    echo "Step 3: Deleting classroom...\n";
    $delClassroom = $pdo->prepare("DELETE FROM classrooms WHERE class_id = ?");
    $delClassroom->execute([$class_id]);
    echo "Step 3 complete. Rows affected: " . $delClassroom->rowCount() . "\n";

    // Step 4: Exams, updates, notifications
    echo "Step 4: Deleting exams, updates, notifications...\n";
    $d1 = $pdo->prepare("DELETE FROM live_exams WHERE class_id = ?");
    $d1->execute([$class_id]);
    $d2 = $pdo->prepare("DELETE FROM class_updates WHERE class_id = ?");
    $d2->execute([$class_id]);
    $d3 = $pdo->prepare("DELETE FROM notifications WHERE class_id = ?");
    $d3->execute([$class_id]);
    echo "Step 4 complete. Rows affected: " . ($d1->rowCount() + $d2->rowCount() + $d3->rowCount()) . "\n";

    echo "Committing transaction...\n";
    $pdo->commit();
    echo "Transaction committed successfully! Deletion works without issues.\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "Transaction rolled back.\n";
    }
    echo "ERROR OCCURRED:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?>
