<?php
/**
 * Upload PDF for AI Study Analysis
 * 1. Validates & saves the PDF file
 * 2. Creates a job record in DB
 * 3. Runs AI processing synchronously
 * 4. Returns final response to app
 */
set_time_limit(300); // 5 minutes for AI processing
ini_set('memory_limit', '512M');

require_once '../config/db.php';
require_once '../config/ai_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$job_id = null; // Track job_id so the catch block can update it

try {
    // 1. Validate Input
    $user_id   = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? intval($_POST['folder_id']) : null;
    if (!$user_id) throw new Exception("Unauthorized: user_id is required");

    // 2. Handle File Upload
    if (!isset($_FILES['pdf_file'])) throw new Exception("No file uploaded");
    $file      = $_FILES['pdf_file'];
    $fileName  = urldecode($file['name']);
    $tmpPath   = $file['tmp_name'];
    if ($file['error'] !== 0) throw new Exception("Upload failed with error code: " . $file['error']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') throw new Exception("Only PDF files are allowed.");
    if ($file['size'] === 0) throw new Exception("File is empty (0 bytes). Your device may have denied storage read permissions.");

    // 3. Save file
    $uploadDir = dirname(__DIR__) . '/uploads/pdf_study/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    
    $uniqueFileName = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $targetPath     = $uploadDir . $uniqueFileName;
    
    // On production/Railway, '/app/backend/uploads' might be read-only if not properly managed,
    // so we fallback to the OS temporary directory (which is safely writable).
    if (!@move_uploaded_file($tmpPath, $targetPath)) {
        $targetPath = sys_get_temp_dir() . '/' . $uniqueFileName;
        if (!@move_uploaded_file($tmpPath, $targetPath)) {
            // Absolute worst-case fallback: read directly from $tmpPath before it expires
            $targetPath = $tmpPath; 
        }
    }

    // 4. Create Job Record (status = pending for worker queue)
    // IMPORTANT: Save the ACTUALLY RESOLVED $targetPath so the AI worker doesn't get lost
    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_path, status, progress, total_pages) VALUES (?, ?, ?, ?, 'pending', 5, 0)");
    $stmt->execute([$user_id, $folder_id, $fileName, $targetPath]);
    $job_id = $pdo->lastInsertId();

    // 5. Instantly return success (Background worker takes over via cron/polling)
    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'success',
        'message'   => 'PDF queued for AI analysis. You will be notified when ready.',
        'job_id'    => $job_id,
        'file_name' => $fileName
    ]);

    // 6. Inline AI Trigger for Local/Fast execution
    // (Non-blocking cURL so user gets response instantly while server processes)
    if (defined('WORKER_SECRET')) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        // Safe base URL extraction
        $baseUri = dirname($_SERVER['SCRIPT_NAME']);
        $workerUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $baseUri . "/pdf_worker_ai.php?key=" . WORKER_SECRET;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $workerUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // 1-second timeout makes it non-blocking
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_exec($ch); // Silence warnings about timeout
        @curl_close($ch);
    }


} catch (Exception $e) {
    $errMsg = $e->getMessage();
    error_log("PDF Upload/AI Error: " . $errMsg);

    // Always try to mark the job as failed with a real error message
    if (!empty($job_id)) {
        try {
            $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")
                ->execute([$errMsg, $job_id]);
        } catch (Exception $dbEx) {
            error_log("Also failed to update job status: " . $dbEx->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $errMsg]);
    exit();
}
?>
