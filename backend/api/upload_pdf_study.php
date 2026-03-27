<?php
/**
 * Upload PDF for AI Study Analysis - Hybrid Mode
 * 1. Saves file immediately & creates job record
 * 2. Returns success instantly to the app 
 * 3. Fires non-blocking HTTP to worker for AI processing
 */
header('Content-Type: application/json');
set_time_limit(60);
ini_set('memory_limit', '256M');

require_once '../config/db.php';
require_once '../config/ai_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    // 1. Validate Input
    $user_id   = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? intval($_POST['folder_id']) : null;
    if (!$user_id) throw new Exception("Unauthorized: user_id is required");

    // 2. Handle File Upload
    if (!isset($_FILES['pdf_file'])) throw new Exception("No file uploaded");
    $file      = $_FILES['pdf_file'];
    $fileName  = $file['name'];
    $tmpPath   = $file['tmp_name'];
    $fileError = $file['error'];
    if ($fileError !== 0) throw new Exception("Upload failed with error code: $fileError");
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') throw new Exception("Only PDF files are allowed.");

    // 3. Save file to uploads directory
    $uploadDir = dirname(__DIR__) . '/uploads/pdf_study/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $safeFileName   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $uniqueFileName = time() . '_' . $user_id . '_' . $safeFileName;
    $targetPath     = $uploadDir . $uniqueFileName;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new Exception("Failed to save file. Upload dir: $uploadDir");
    }

    // 4. Create Job Record in DB
    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_path, status, progress, total_pages) VALUES (?, ?, ?, ?, 'pending', 10, 0)");
    $stmt->execute([$user_id, $folder_id, $fileName, $uniqueFileName]);
    $job_id = $pdo->lastInsertId();

    // 5. Fire-and-forget: trigger the AI worker
    $protocol   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $workerUrl  = "$protocol://$host/backend/api/pdf_worker_ai.php";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $workerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); // abandon after 0.5s - worker continues running
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    @curl_exec($ch);
    curl_close($ch);

    // 6. Return immediate success to the app
    echo json_encode([
        'status'    => 'success',
        'message'   => 'PDF uploaded! AI is processing...',
        'job_id'    => $job_id,
        'file_name' => $fileName
    ]);

} catch (Exception $e) {
    error_log("PDF Upload Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
