<?php
require_once 'backend/config/db.php';
$stmt = $pdo->query("SELECT job_id, user_id, file_name, status, progress, error_message, created_at FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 10");
$jobs = $stmt->fetchAll();
foreach ($jobs as $job) {
    echo "Job #{$job['job_id']} (User {$job['user_id']}): {$job['file_name']} - Status: {$job['status']} ({$job['progress']}%) - Error: " . ($job['error_message'] ?: 'none') . " - Created: {$job['created_at']}\n";
}
?>
