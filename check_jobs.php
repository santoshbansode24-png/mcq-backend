<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=veeru_db;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT job_id, file_name, status, error_message FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
