<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // 1. Check current unique values
    $stmt = $pdo->query("SELECT task_type, COUNT(*) as count FROM study_tasks GROUP BY task_type");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Identify the specific row (row 75 is approximately ID 75 or similar in the current insertion order)
    // We'll search for anything that might look suspicious
    $stmt2 = $pdo->query("SELECT * FROM study_tasks LIMIT 70, 10");
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'column_stats' => $stats,
        'surrounding_rows' => $rows
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
