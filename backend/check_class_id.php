<?php
header('Content-Type: application/json');
require_once 'config/db.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE class_name LIKE '%Scholarship%'");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['classes' => $classes], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
