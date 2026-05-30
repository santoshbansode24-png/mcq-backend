<?php
require 'config/db.php';
try {
    $class_id = 10;
    $teacher_id = 1;
    $school_name = 'Test School';
    $title = 'New Worksheet: Mathematics';
    $message = 'A new worksheet has been generated for Mathematics. Please complete it.';
    $payload = json_encode([]);
    $update_type = 'worksheet';
    
    $stmt = $pdo->prepare("
        INSERT INTO class_updates (class_id, teacher_id, school_name, title, message, payload, update_type, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$class_id, $teacher_id, $school_name, $title, $message, $payload, $update_type]);
    echo "Insert successful. ID: " . $pdo->lastInsertId();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
