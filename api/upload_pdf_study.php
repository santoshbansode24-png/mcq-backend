<?php
/**
 * PDF & Multi-Image Study Job Upload API
 * Handles single PDF files OR multiple photo uploads (camera snaps & gallery images)
 */
require_once __DIR__ . '/cors_middleware.php';
set_time_limit(300);
ini_set('memory_limit', '512M');

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} else {
    require_once __DIR__ . '/../config/db.php';
}
require_once __DIR__ . '/../config/ai_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

/**
 * Pure PHP Image-to-PDF Converter
 * Converts an array of uploaded image temporary files into a multi-page PDF base64 string
 */
function convertImagesToPdfBase64($tmpFiles) {
    $jpegStreams = [];
    foreach ($tmpFiles as $tmpPath) {
        if (empty($tmpPath) || !file_exists($tmpPath) || filesize($tmpPath) === 0) continue;
        $imgData = file_get_contents($tmpPath);
        if (!$imgData) continue;
        $im = @imagecreatefromstring($imgData);
        if ($im !== false) {
            $w = imagesx($im) ?: 612;
            $h = imagesy($im) ?: 792;
            ob_start();
            imagejpeg($im, null, 80);
            $jpgBytes = ob_get_clean();
            imagedestroy($im);
            $jpegStreams[] = [
                'bytes'  => $jpgBytes,
                'width'  => $w,
                'height' => $h
            ];
        }
    }
    
    if (empty($jpegStreams)) {
        throw new Exception("No valid images could be read. Please upload clear JPG or PNG photos.");
    }
    
    $numPages = count($jpegStreams);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    
    // Object 1: Catalog
    $offsets[1] = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    
    // Object 2: Pages
    $kids = "";
    for ($i = 0; $i < $numPages; $i++) {
        $pageObjNum = 3 + ($i * 3);
        $kids .= "{$pageObjNum} 0 R ";
    }
    $offsets[2] = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [ $kids] /Count $numPages >>\nendobj\n";
    
    for ($i = 0; $i < $numPages; $i++) {
        $pageObjNum = 3 + ($i * 3);
        $contentObjNum = $pageObjNum + 1;
        $imageObjNum = $pageObjNum + 2;
        
        $img = $jpegStreams[$i];
        $w = $img['width'];
        $h = $img['height'];
        
        $offsets[$pageObjNum] = strlen($pdf);
        $pdf .= "{$pageObjNum} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentObjNum} 0 R /Resources << /XObject << /Im{$i} {$imageObjNum} 0 R >> >> >>\nendobj\n";
        
        $stream = "q 612 0 0 792 0 0 cm /Im{$i} Do Q";
        $offsets[$contentObjNum] = strlen($pdf);
        $pdf .= "{$contentObjNum} 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";
        
        $offsets[$imageObjNum] = strlen($pdf);
        $pdf .= "{$imageObjNum} 0 obj\n<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($img['bytes']) . " >>\nstream\n" . $img['bytes'] . "\nendstream\nendobj\n";
    }
    
    $startXref = strlen($pdf);
    $numObjects = 2 + ($numPages * 3);
    $pdf .= "xref\n0 " . ($numObjects + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $numObjects; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . ($numObjects + 1) . " /Root 1 0 R >>\nstartxref\n$startXref\n%%EOF";
    
    return base64_encode($pdf);
}

$job_id = null;

try {
    // --- 1. Validate Input ---
    $user_id   = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? intval($_POST['folder_id']) : null;
    $difficulty= isset($_POST['difficulty']) ? $_POST['difficulty'] : 'mix';
    
    if (!in_array($difficulty, ['easy', 'moderate', 'hard', 'mix'])) $difficulty = 'mix';
    if (!$user_id) throw new Exception("Unauthorized: user_id is required");

    $pdfBase64 = '';
    $fileName  = 'Scanned Study Pack';

    // --- 2. Check for Multi-Image Files Upload OR Single PDF File ---
    $imageTmpFiles = [];
    if (isset($_FILES['image_files'])) {
        $files = $_FILES['image_files'];
        if (is_array($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $tmp) {
                if (!empty($tmp) && file_exists($tmp)) {
                    $imageTmpFiles[] = $tmp;
                }
            }
        } else if (!empty($files['tmp_name']) && file_exists($files['tmp_name'])) {
            $imageTmpFiles[] = $files['tmp_name'];
        }
    }

    if (!empty($imageTmpFiles)) {
        // Multi-Image Upload Mode
        $count = count($imageTmpFiles);
        $customTitle = isset($_POST['custom_file_name']) && !empty($_POST['custom_file_name']) ? $_POST['custom_file_name'] : '';
        $fileName = $customTitle ?: "Photo Study Pack ($count Pages).pdf";
        $pdfBase64 = convertImagesToPdfBase64($imageTmpFiles);
    } elseif (isset($_FILES['pdf_file'])) {
        // Single PDF Upload Mode
        $file = $_FILES['pdf_file'];
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

        $headerName = isset($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) && !empty($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) ? urldecode($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) : '';
        $postName = isset($_POST['custom_file_name']) && !empty($_POST['custom_file_name']) ? $_POST['custom_file_name'] : '';
        $rawName = $headerName ?: ($postName ?: $file['name']);
        
        $decoded = rawurldecode($rawName);
        if (!mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = urldecode($rawName);
        }
        if (!mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = mb_convert_encoding($rawName, 'UTF-8', mb_detect_encoding($rawName));
        }
        $fileName = $decoded ?: $rawName;
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf' && $ext !== 'jpg' && $ext !== 'jpeg' && $ext !== 'png') {
            throw new Exception("Allowed file formats: PDF, JPG, PNG. Got: '$ext'");
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $pdfBase64 = convertImagesToPdfBase64([$tmpPath]);
            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.pdf';
        } else {
            $pdfBytes = file_get_contents($tmpPath);
            if ($pdfBytes === false || strlen($pdfBytes) < 100) {
                throw new Exception("Failed to read uploaded PDF file.");
            }
            $pdfBase64 = base64_encode($pdfBytes);
            unset($pdfBytes);
        }
    } else {
        throw new Exception("No PDF or Image files uploaded.");
    }

    // --- 3. Save Record into MySQL DB ---
    $fileHash = md5($pdfBase64);
    $fileSize = strlen($pdfBase64);

    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_hash, file_size, pdf_base64, difficulty, status, current_step) VALUES (?, ?, ?, ?, ?, ?, ?, 'processing', 'Queued for AI extraction')");
    $stmt->execute([$user_id, $folder_id, $fileName, $fileHash, $fileSize, $pdfBase64, $difficulty]);
    $job_id = $pdo->lastInsertId();

    // --- 4. Trigger Async AI Worker ---
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $workerUrl = "$scheme://$host/backend/api/pdf_worker_ai.php?job_id=$job_id";

    // Non-blocking cURL call
    $ch = curl_init($workerUrl);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    @curl_exec($ch);
    curl_close($ch);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Study job uploaded and queued successfully.',
        'job_id'  => $job_id,
        'file_name' => $fileName
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
