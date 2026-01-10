<?php
require_once '../config/db.php';
try {
    $stmt = $pdo->query("
        SELECT c.chapter_id, c.chapter_name, s.subject_name, cl.class_name 
        FROM chapters c
        JOIN subjects s ON c.subject_id = s.subject_id
        JOIN classes cl ON s.class_id = cl.class_id
        WHERE c.chapter_id = 15
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Chapter 15: '{$row['chapter_name']}'\n";
        echo "Subject: '{$row['subject_name']}'\n";
        echo "Class: '{$row['class_name']}'\n";
    } else {
        echo "Chapter 15 not found.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
