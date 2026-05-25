<?php
require_once 'backend/config/db.php';
$stmt = $pdo->query("SELECT job_id, user_id, file_name, file_path, status, CHAR_LENGTH(pdf_base64) as b64_len, CHAR_LENGTH(extracted_text) as text_len, created_at FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 5");
$jobs = $stmt->fetchAll();
foreach ($jobs as $job) {
    echo "Job #{$job['job_id']}: {$job['file_name']} - Path: '{$job['file_path']}' - B64 Len: {$job['b64_len']} - Text Len: {$job['text_len']}\n";
}
?>
