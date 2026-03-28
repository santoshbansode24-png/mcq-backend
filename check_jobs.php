<?php
require 'backend/config/db.php';
$stmt = $pdo->query("SELECT job_id, status, progress, error_message, LENGTH(study_content) as content_length FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
