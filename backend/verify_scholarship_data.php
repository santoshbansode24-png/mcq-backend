<?php
header('Content-Type: application/json');
require_once 'config/db.php';

try {
    // 1. Check Class 37
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE class_id = ?");
    $stmt->execute([37]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Check Subjects for Class 37
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE class_id = ?");
    $stmt->execute([37]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'class_37_exists' => $class ? true : false,
        'class_details' => $class,
        'subjects_count' => count($subjects),
        'subjects' => $subjects
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
