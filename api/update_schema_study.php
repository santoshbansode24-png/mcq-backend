<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM pdf_study_jobs");
    $actualCols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'columns' => $actualCols
    ], JSON_PRETTY_PRINT);
    exit();
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
