<?php
require_once 'config/db.php';

$tables = ['live_exams', 'student_exam_attempts', 'student_class_mapping', 'classrooms', 'teacher_classes', 'users'];

echo "--- DATABASE SCHEMA AUDIT ---\n\n";

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "TABLE: $table\n";
        echo $row['Create Table'] . "\n\n";
    } catch (PDOException $e) {
        echo "TABLE $table does not exist or error: " . $e->getMessage() . "\n\n";
    }
}
?>
