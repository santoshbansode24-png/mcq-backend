<?php
/**
 * Upload PDF for AI Study Analysis — Permanent Production Fix
 *
 * KEY FIX: PDF bytes are read into base64 and stored in the DB IMMEDIATELY,
 * so the AI worker never depends on the filesystem (which is ephemeral on Railway /tmp).
 *
 * Also fixes:
 * - Marathi / Hindi filename decode from Android DocumentPicker
 * - Proper CORS headers
 * - Clear error codes from PHP upload errors
 */
require_once 'cors_middleware.php'; // Handles CORS, error reporting, and JSON header
set_time_limit(300);
ini_set('memory_limit', '512M');

require_once '../config/db.php';
require_once '../config/ai_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$job_id = null;

// --- 0. Logging Request (Diagnostic) ---
$logData = [
    'user_id'   => $_POST['user_id'] ?? 'MISSING',
    'folder_id' => $_POST['folder_id'] ?? 'root',
    'file'      => $_FILES['pdf_file']['name'] ?? 'NONE',
    'size'      => $_FILES['pdf_file']['size'] ?? 0
];
error_log("[Veeru] PDF Upload Start: " . json_encode($logData));

try {
    // --- 1. Validate Input ---
    $user_id   = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? intval($_POST['folder_id']) : null;
    if (!$user_id) throw new Exception("Unauthorized: user_id is required");

    // --- 2. Validate uploaded file ---
    if (!isset($_FILES['pdf_file'])) throw new Exception("No file uploaded");
    $file    = $_FILES['pdf_file'];
    $tmpPath = $file['tmp_name'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $phpErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File too large (server limit). Max allowed is ' . ini_get('upload_max_filesize'),
            UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Server cannot write file to disk',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension',
        ];
        throw new Exception($phpErrors[$file['error']] ?? "Upload error code: " . $file['error']);
    }
    if ($file['size'] === 0) throw new Exception("File is empty (0 bytes). Check your device storage permissions.");

    // --- 3. Decode filename properly (handles Marathi/Hindi/URL-encoded names from Android) ---
    $headerName = isset($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) && !empty($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) ? urldecode($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) : '';
    $postName = isset($_POST['custom_file_name']) && !empty($_POST['custom_file_name']) ? $_POST['custom_file_name'] : '';
    $rawName = $headerName ?: ($postName ?: $file['name']);
    
    // Try rawurldecode first, verify it's valid UTF-8
    $decoded = rawurldecode($rawName);
    if (!mb_check_encoding($decoded, 'UTF-8')) {
        $decoded = urldecode($rawName);
    }
    if (!mb_check_encoding($decoded, 'UTF-8')) {
        $decoded = mb_convert_encoding($rawName, 'UTF-8', mb_detect_encoding($rawName));
    }
    $fileName = $decoded ?: $rawName;
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') throw new Exception("Only PDF files are allowed. Got: '$ext'");

    // --- 4. Read PDF bytes immediately from tmp (before PHP deletes it at end of request) ---
    // CRITICAL FIX: On Railway, /tmp is ephemeral between requests.
    // We store base64 in the DB so the worker ALWAYS has access to the PDF data.
    $pdfBytes = file_get_contents($tmpPath);
    if ($pdfBytes === false || strlen($pdfBytes) < 100) {
        throw new Exception("Failed to read uploaded PDF. File may be corrupted or empty.");
    }
    $pdfBase64 = base64_encode($pdfBytes);
    unset($pdfBytes);

    // --- 5. Try saving to disk as well (optional, for large PDF serving) ---
    $savedPath = '';
    $uploadDir = dirname(__DIR__) . '/uploads/pdf_study/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
    $safeName = time() . '_' . $user_id . '_study.pdf';
    if (@move_uploaded_file($tmpPath, $uploadDir . $safeName)) {
        $savedPath = $uploadDir . $safeName;
    } elseif (@copy($tmpPath, sys_get_temp_dir() . '/' . $safeName)) {
        $savedPath = sys_get_temp_dir() . '/' . $safeName;
    }

    // --- 6. ADD MISSING COLUMNS (Improved Compatibility) ---
    // MySQL 5.7 / Standard 8.0 doesn't support IF NOT EXISTS for ADD COLUMN.
    // We check if the column exists manually to prevent crashes.
    $checkCol = $pdo->query("SHOW COLUMNS FROM pdf_study_jobs LIKE 'pdf_base64'");
    if (!$checkCol->fetch()) {
        try {
            $pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN pdf_base64 LONGTEXT DEFAULT NULL AFTER file_path");
            $pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN folder_id INT DEFAULT NULL AFTER user_id");
            $pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN study_content LONGTEXT DEFAULT NULL AFTER pdf_base64");
            $pdo->exec("ALTER TABLE pdf_study_jobs ADD INDEX (folder_id)");
        } catch (Exception $migEx) {
            // Ignore if column/index already exists
        }
    }

    // --- 6.5 CHECK DATABASE PACKET SIZE (Pre-flight) ---
    // MySQL session packet limit may be restricted by the global setting
    $packetStmt  = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
    $packetVar   = $packetStmt->fetch();
    $maxPacket   = (int)$packetVar['Value'];
    $payloadSize = strlen($pdfBase64);
    
    // Warn if base64 is close to or exceeds the limit
    if ($payloadSize > ($maxPacket * 0.95)) {
        error_log("[Veeru] PDF Size Warning: $payloadSize bytes is close to max_allowed_packet ($maxPacket).");
        throw new Exception("PDF is too large for database storage ($payloadSize bytes). " .
            "The database 'max_allowed_packet' is currently " . round($maxPacket / 1024 / 1024, 1) . "MB. " .
            "Please ask administrator to increase it in my.ini (e.g., max_allowed_packet=64M).");
    }

    // --- 7. Create Job Record with base64 payload ---
    $stmt = $pdo->prepare(
        "INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_path, pdf_base64, status, progress, total_pages)
         VALUES (?, ?, ?, ?, ?, 'pending', 5, 0)"
    );
    $stmt->execute([$user_id, $folder_id, $fileName, $savedPath, $pdfBase64]);
    $job_id = $pdo->lastInsertId();
    unset($pdfBase64);

    // --- 8. Return success to app immediately ---
    echo json_encode([
        'status'    => 'success',
        'message'   => 'PDF queued for AI analysis.',
        'job_id'    => intval($job_id),
        'file_name' => $fileName
    ]);

    // --- 9. Trigger AI worker (non-blocking, fire-and-forget) ---
    // We use a slight delay or non-blocking curl to trigger the background processing.
    if (defined('WORKER_SECRET') && function_exists('curl_init')) {
        $isHttps   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $protocol  = $isHttps ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'];
        $baseUri   = dirname($_SERVER['SCRIPT_NAME']);
        $workerUrl = $protocol . '://' . $host . $baseUri . '/pdf_worker_ai.php?key=' . WORKER_SECRET;

        // Fire-and-forget trigger
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $workerUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500); // Increased timeout for slow handshakes
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1); // Important for short timeouts in multi-threaded env
        @curl_exec($ch);
        @curl_close($ch);
    }

} catch (Exception $e) {
    $errMsg = $e->getMessage();
    error_log("[Veeru] PDF Upload Error: " . $errMsg);

    if (!empty($job_id)) {
        try {
            $pdo->prepare("UPDATE pdf_study_jobs SET status='failed', error_message=? WHERE job_id=?")
                ->execute([$errMsg, $job_id]);
        } catch (Exception $dbEx) {
            error_log("[Veeru] Also failed to update job: " . $dbEx->getMessage());
        }
    }

    // Clean JSON response for error
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'error', 'message' => $errMsg]);
    exit();
}
?>
