<?php
require 'config/db.php';
try {
    $class_id = 10;
    $teacher_id = 1;
    $school_name = 'Test School';
    $title = 'New Worksheet: Mathematics';
    $message = 'A new worksheet has been generated for Mathematics. Please complete it.';
    $payloadJson = json_encode(['file_url' => 'test', 'file_name' => 'test.pdf']);
    $update_type = 'worksheet';
    
    $stmt = $pdo->prepare("
        INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $teacher_id,
        $school_name,
        $class_id,
        $update_type,
        $title,
        $message,
        $payloadJson
    ]);
    echo "Insert successful. ID: " . $pdo->lastInsertId();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
