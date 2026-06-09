<?php
require 'backend/config/db.php';
$stmt = $pdo->query("SELECT job_id, status, progress, error_message, difficulty FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 5");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('jobs_output.txt', print_r($jobs, true));
echo "Saved to jobs_output.txt";
?>
