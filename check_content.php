<?php
require 'backend/config/db.php';
$stmt = $pdo->query('SELECT job_id, status, SUBSTR(study_content, 1, 150) as snippet FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
