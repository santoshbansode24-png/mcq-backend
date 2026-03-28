<?php
require 'backend/config/db.php';
$stmt = $pdo->query('SELECT job_id, status, error_message FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 1');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
