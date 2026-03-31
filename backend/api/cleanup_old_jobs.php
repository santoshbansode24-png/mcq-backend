<?php
require_once '../config/db.php';

echo "<h2>Cleaning Up Failed PDF Jobs</h2>";

try {
    // 1. Get all failed jobs
    $stmt = $pdo->prepare("SELECT job_id, file_path FROM pdf_study_jobs WHERE status = 'failed'");
    $stmt->execute();
    $failedJobs = $stmt->fetchAll();

    $deletedCount = 0;
    $fileSpaceSaved = 0;

    foreach ($failedJobs as $job) {
        $jobId = $job['job_id'];
        
        // 2. Delete the physical file if it exists
        if (!empty($job['file_path'])) {
            $fullPath = __DIR__ . '/../uploads/pdf_study/' . $job['file_path'];
            if (file_exists($fullPath) && is_file($fullPath)) {
                $fileSpaceSaved += filesize($fullPath);
                unlink($fullPath);
            }
        }

        // 3. Delete from database
        $pdo->prepare("DELETE FROM pdf_study_jobs WHERE job_id = ?")->execute([$jobId]);
        $deletedCount++;
    }

    $mbSaved = round($fileSpaceSaved / 1024 / 1024, 2);

    echo "<p>✅ Cleanup Complete.</p>";
    echo "<ul>";
    echo "<li><b>Deleted Jobs:</b> $deletedCount</li>";
    echo "<li><b>Recovered Storage Space:</b> $mbSaved MB</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
