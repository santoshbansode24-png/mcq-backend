<?php
/**
 * Upload PDF for AI Study Analysis
 * Part of the PDF-to-Exam Feature
 */

header('Content-Type: application/json');
require_once '../config/db.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    // 1. Validate Input
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        throw new Exception("Unauthorized: user_id is required");
    }

    // 2. Handle File Upload
    if (!isset($_FILES['pdf_file'])) {
        throw new Exception("No file uploaded");
    }

    $file = $_FILES['pdf_file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileType = $file['type'];
    $fileError = $file['error'];

    // Basic MIME check
    if ($fileType !== 'application/pdf' && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf') {
        throw new Exception("Invalid file type. Only PDF allowed.");
    }

    if ($fileError !== 0) {
        throw new Exception("Upload failed with error code: $fileError");
    }

    // 3. Create Secure Path
    $uploadDir = '../../uploads/pdf_study/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $uniqueFileName = time() . '_' . $user_id . '_' . $safeFileName;
    $finalPath = $uploadDir . $uniqueFileName;

    if (!move_uploaded_file($fileTmpName, $finalPath)) {
        throw new Exception("Failed to move uploaded file to permanent storage");
    }

    // 4. Create Background Job
    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, file_name, file_path, status, progress, total_pages) VALUES (?, ?, ?, 'pending', 0, 0)");
    $stmt->execute([$user_id, $fileName, $uniqueFileName]);
    $job_id = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'PDF uploaded successfully. Processing started.',
        'job_id' => $job_id,
        'file_name' => $fileName
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
