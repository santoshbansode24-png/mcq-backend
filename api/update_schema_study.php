<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$res = [];
try {
    $pdo->exec("ALTER TABLE pdf_study_jobs MODIFY COLUMN file_path VARCHAR(512) DEFAULT ''");
    $res['modify_file_path'] = 'SUCCESS';
} catch (PDOException $e) {
    $res['modify_file_path'] = $e->getMessage();
}

try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM pdf_study_jobs");
    $actualCols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
    $res['columns'] = $actualCols;
} catch (PDOException $e) {
    $res['cols_error'] = $e->getMessage();
}

echo json_encode($res, JSON_PRETTY_PRINT);
exit();
?>
