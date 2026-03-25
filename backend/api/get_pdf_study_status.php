<?php
/**
 * Get PDF Study Job Status
 * Part of the PDF-to-Exam Feature
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    if ($job_id) {
        $stmt = $pdo->prepare("SELECT job_id, user_id, file_name, status, progress, total_pages, processed_pages, error_message, created_at, updated_at FROM pdf_study_jobs WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$job_id, $user_id]);
        $job = $stmt->fetch();
        
        if (!$job) {
            throw new Exception("Job not found");
        }
        
        echo json_encode(['status' => 'success', 'data' => $job]);
    } else {
        // Get all jobs for this user (for the library screen)
        $stmt = $pdo->prepare("SELECT job_id, user_id, file_name, status, progress, total_pages, processed_pages, created_at FROM pdf_study_jobs WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $jobs = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $jobs]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
