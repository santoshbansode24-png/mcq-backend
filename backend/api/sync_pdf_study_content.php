<?php
/**
 * Sync and Cleanup PDF Study Content
 * Part of the Veeru Lens Feature
 * Pattern: Disposable Server (Upload -> Process -> Sync -> Wipe)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Essential for React Native fetch
require_once '../config/db.php';

// Support both POST and JSON input (React Native sometimes sends JSON body)
$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
$job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : (isset($input['job_id']) ? intval($input['job_id']) : 0);
$action = isset($_POST['action']) ? $_POST['action'] : (isset($input['action']) ? $input['action'] : 'fetch');

if (!$user_id || !$job_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing User ID or Job ID']);
    exit();
}

try {
    if ($action === 'fetch') {
        // 1. Fetch the generated JSON study pack
        $stmt = $pdo->prepare("SELECT study_pack_json, is_synced FROM pdf_study_content WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$job_id, $user_id]);
        $row = $stmt->fetch();

        if (!$row) {
            // Check if it's still processing — enforce user ownership here too
            $checkJob = $pdo->prepare("SELECT status FROM pdf_study_jobs WHERE job_id = ? AND user_id = ?");
            $checkJob->execute([$job_id, $user_id]);
            $jobStatus = $checkJob->fetch();

            $msg = ($jobStatus && $jobStatus['status'] === 'processing')
                ? "AI is still analyzing your PDF. Please wait..."
                : "Study pack not found. It may have already been synced and wiped.";

            throw new Exception($msg);
        }

        echo json_encode([
            'status' => 'success',
            'study_pack' => json_decode($row['study_pack_json'], true),
            'is_synced' => (bool) $row['is_synced']
        ]);

    } else if ($action === 'acknowledge') {
        /**
         * PHONE ACKNOWLEDGEMENT: 
         * The mobile app confirms it has saved the JSON locally.
         * We now wipe the heavy data from the server.
         */

        // A. Find the physical PDF file to delete
        $stmt = $pdo->prepare("SELECT file_path FROM pdf_study_jobs WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$job_id, $user_id]);
        $job = $stmt->fetch();

        if ($job && !empty($job['file_path'])) {
            $filePath = $job['file_path'];
            // Smart Absolute Path check (supports local XAMPP & Railway)
            if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
                $fullPath = __DIR__ . '/../uploads/pdf_study/' . $filePath;
            } else {
                $fullPath = $filePath;
            }
            
            if (file_exists($fullPath) && is_file($fullPath)) {
                unlink($fullPath);
            }
        }

        // B. Clear the heavy JSON from pdf_study_content
        $pdo->prepare("DELETE FROM pdf_study_content WHERE job_id = ?")->execute([$job_id]);

        // C. Clear the pdf_base64 blob from pdf_study_jobs (major storage saving — PDFs are 10-20MB as base64)
        $pdo->prepare("UPDATE pdf_study_jobs SET pdf_base64 = NULL, file_path = '' WHERE job_id = ? AND user_id = ?")
            ->execute([$job_id, $user_id]);

        // D. Update Job Status to reflect cleanup
        $update = $pdo->prepare("UPDATE pdf_study_jobs SET status = 'completed', progress = 100 WHERE job_id = ?");
        $update->execute([$job_id]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Privacy Protocol: Server-side PDF and JSON wiped. Data exists only on your device.'
        ]);
    } else {
        throw new Exception("Invalid action requested.");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>