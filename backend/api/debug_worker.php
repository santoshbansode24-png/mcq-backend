<?php
/**
 * Manual AI Worker Trigger
 * Visit this in your browser: https://api.veeruapp.in/backend/api/debug_worker.php
 */
require_once 'ai_helpers.php';
require_once '../config/db.php';

echo "<h2>AI Worker Debug Mode</h2>";

// 1. Check for pending jobs
$stmt = $pdo->query("SELECT COUNT(*) FROM pdf_study_jobs WHERE status = 'pending'");
$pendingCount = $stmt->fetchColumn();

echo "<p>Pending Jobs: <strong>$pendingCount</strong></p>";

if ($pendingCount > 0) {
    echo "<p>🔄 Triggering worker now...</p>";
    
    // We run it directly here so the user can see the output
    include 'pdf_worker_ai.php';
} else {
    echo "<p>✅ No pending jobs. Checking if any are 'processing' but stuck...</p>";
    
    $stmt = $pdo->query("SELECT * FROM pdf_study_jobs WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $stuckJobs = $stmt->fetchAll();
    
    if (count($stuckJobs) > 0) {
        echo "<p>⚠️ Found " . count($stuckJobs) . " stuck jobs. Resetting them to pending...</p>";
        foreach ($stuckJobs as $job) {
            $pdo->prepare("UPDATE pdf_study_jobs SET status = 'pending' WHERE job_id = ?")->execute([$job['job_id']]);
            echo "<li>Reset Job #" . $job['job_id'] . " (" . $job['file_name'] . ")</li>";
        }
        echo "<p>Please refresh this page to start processing them.</p>";
    } else {
        echo "<p>Everything looks fine. If the app shows 0%, try uploading a fresh PDF.</p>";
    }
}
?>
