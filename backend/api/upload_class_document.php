<?php
/**
 * Upload Class Document API
 * Receives a multipart file upload and saves it to the server.
 * Returns the URL of the uploaded file.
 */
require_once 'cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed']);
    exit();
}

try {
    if (!isset($_FILES['file'])) {
        throw new Exception("No file uploaded");
    }

    $file = $_FILES['file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload error code: " . $file['error']);
    }

    $uploadDir = dirname(__DIR__) . '/uploads/class_documents/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($ext) && $file['type'] === 'application/pdf') {
        $ext = 'pdf';
    } else if (empty($ext)) {
        $ext = 'pdf'; // Default fallback for worksheets
    }

    $safeName = time() . '_' . uniqid() . '.' . $ext;

    // Cloudflare R2 Upload Integration
    require_once dirname(__DIR__) . '/config/aws-config.php';
    $r2Url = false;
    if (isR2Configured()) {
        $s3_key = 'class_documents/' . $safeName;
        $r2Url = uploadToS3($file['tmp_name'], $s3_key);
    }

    if ($r2Url) {
        $fileUrl = $r2Url;
    } else {
        $destPath = $uploadDir . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception("Failed to move uploaded file");
        }

        // Determine the base URL
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . '://' . $host . dirname(dirname($_SERVER['SCRIPT_NAME'])); // points to backend/

        $fileUrl = $baseUrl . '/uploads/class_documents/' . $safeName;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'File uploaded successfully',
        'url' => $fileUrl
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
