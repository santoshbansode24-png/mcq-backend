<?php
/**
 * Get PDF Study Job Status
 * Part of the PDF-to-Exam Feature
 */

header('Content-Type: application/json');
require_once '../config/db.php';
require_once 'ai_helpers.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$folder_id = isset($_GET['folder_id']) ? $_GET['folder_id'] : null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    if ($job_id) {
        $stmt = $pdo->prepare("SELECT job_id, user_id, file_name, status, progress, total_pages, processed_pages, error_message, study_content, created_at, updated_at FROM pdf_study_jobs WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$job_id, $user_id]);
        $job = $stmt->fetch();
        
        if (!$job) {
            throw new Exception("Job not found");
        }
        
        echo json_encode(['status' => 'success', 'data' => $job]);
    } else {
        // Get jobs for this user (filtered by folder if provided)
        $sql = "SELECT job_id, user_id, folder_id, file_name, status, progress, total_pages, processed_pages, study_content, created_at FROM pdf_study_jobs WHERE user_id = ?";
        $params = [$user_id];
        
        if ($folder_id === 'root') {
            $sql .= " AND (folder_id IS NULL OR folder_id = 0)";
        } else if ($folder_id !== null && is_numeric($folder_id)) {
            $sql .= " AND folder_id = ?";
            $params[] = intval($folder_id);
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();
        
        // Worker trigger removed to prevent race conditions with inline processing
        
        echo json_encode(['status' => 'success', 'data' => $jobs]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
