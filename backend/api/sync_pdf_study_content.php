<?php
/**
 * Sync and Cleanup PDF Study Content
 * Part of the PDF-to-Exam Feature
 * Implements the "Disposable Server" pattern.
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$job_id  = isset($_POST['job_id'])  ? intval($_POST['job_id'])  : 0;
$action  = isset($_POST['action'])  ? $_POST['action']           : 'fetch';

if (!$user_id || !$job_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

try {
    if ($action === 'fetch') {
        // 1. Fetch the generated JSON
        $stmt = $pdo->prepare("SELECT study_pack_json FROM pdf_study_content WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$job_id, $user_id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception("Study pack not found or not ready yet.");
        }

        echo json_encode([
            'status' => 'success', 
            'study_pack' => json_decode($row['study_pack_json'], true)
        ]);

    } else if ($action === 'acknowledge') {
        // 2. Phone confirms it has the data. Wipe server-side files.
        
        // A. Get file path
        $stmt = $pdo->prepare("SELECT file_path FROM pdf_study_jobs WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$job_id, $user_id]);
        $job = $stmt->fetch();

        if ($job && !empty($job['file_path'])) {
            $fullPath = '../../uploads/pdf_study/' . $job['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath); // Delete physical PDF
            }
        }

        // B. Mark as synced and delete temporary JSON
        $pdo->prepare("UPDATE pdf_study_content SET is_synced = 1 WHERE job_id = ?")->execute([$job_id]);
        $pdo->prepare("DELETE FROM pdf_study_content WHERE job_id = ?")->execute([$job_id]);
        
        // C. Update Job Status to reflect cleanup
        $pdo->prepare("UPDATE pdf_study_jobs SET status = 'completed', progress = 100 WHERE job_id = ?")->execute([$job_id]);

        echo json_encode(['status' => 'success', 'message' => 'Server cleanup complete. Data is now only on user device.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
