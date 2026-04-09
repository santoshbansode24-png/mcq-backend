<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query("SELECT task_type, COUNT(*) as count FROM study_tasks GROUP BY task_type");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt2 = $pdo->query("SELECT * FROM study_tasks LIMIT 5");
    $samples = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'counts' => $counts,
        'samples' => $samples
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
