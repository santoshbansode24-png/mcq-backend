<?php
require_once '../config/db.php';
$stmt=$pdo->query('SELECT file_name, status, error_message FROM pdf_study_jobs WHERE status="failed" ORDER BY job_id DESC LIMIT 5');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
