<?php
require 'config/db.php';
try {
    $teacher_id = 1; // Dummy teacher ID
    $class_code = "ABCDEF";
    $full_class_name = "Class 10 - A";
    $board = "State Board";
    $medium = "Marathi";
    $class_level = 10;

    $stmt_c = $pdo->prepare("
        INSERT INTO classrooms (teacher_id, class_code, class_name, board, medium, class_level) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt_c->execute([$teacher_id, $class_code, $full_class_name, $board, $medium, $class_level]);
    echo "Success!";
} catch (PDOException $e) {
    echo "Classrooms Insert Error: " . $e->getMessage();
}
?>
