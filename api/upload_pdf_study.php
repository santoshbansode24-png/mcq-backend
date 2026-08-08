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
require_once __DIR__ . '/AiUsageManager.php';

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
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($tmpPath);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3: $im = imagerotate($im, 180, 0); break;
                        case 6: $im = imagerotate($im, -90, 0); break;
                        case 8: $im = imagerotate($im, 90, 0); break;
                    }
                }
            }
            $w = imagesx($im) ?: 612;
            $h = imagesy($im) ?: 792;
            ob_start();
            imagejpeg($im, null, 85);
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
    
    // Object 2: Pages tree
    $pageKids = [];
    for ($i = 0; $i < $numPages; $i++) {
        $pageKids[] = (3 + $i * 3) . " 0 R";
    }
    $offsets[2] = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $pageKids) . "] /Count $numPages >>\nendobj\n";
    
    // Build page objects & streams
    for ($i = 0; $i < $numPages; $i++) {
        $pageObjId  = 3 + $i * 3;
        $contObjId  = 4 + $i * 3;
        $imageObjId = 5 + $i * 3;
        $streamInfo = $jpegStreams[$i];
        $w = $streamInfo['width'];
        $h = $streamInfo['height'];
        
        // Page Object
        $offsets[$pageObjId] = strlen($pdf);
        $pdf .= "$pageObjId 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h] /Contents $contObjId 0 R /Resources << /XObject << /Im$i $imageObjId 0 R >> >> >>\nendobj\n";
        
        // Content stream
        $streamContent = "q $w 0 0 $h 0 0 cm /Im$i Do Q";
        $offsets[$contObjId] = strlen($pdf);
        $pdf .= "$contObjId 0 obj\n<< /Length " . strlen($streamContent) . " >>\nstream\n$streamContent\nendstream\nendobj\n";
        
        // Image object
        $offsets[$imageObjId] = strlen($pdf);
        $imgLen = strlen($streamInfo['bytes']);
        $pdf .= "$imageObjId 0 obj\n<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length $imgLen >>\nstream\n" . $streamInfo['bytes'] . "\nendstream\nendobj\n";
    }
    
    // Xref table
    $xrefStart = strlen($pdf);
    $numObjs = count($offsets);
    $pdf .= "xref\n0 $numObjs\n0000000000 65535 f \n";
    for ($i = 1; $i < $numObjs; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size $numObjs /Root 1 0 R >>\nstartxref\n$xrefStart\n%%EOF";
    
    return base64_encode($pdf);
}

try {
    // --- 1. Check Parameters & Quota ---
    $user_id    = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $folder_id  = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
    $difficulty = isset($_POST['difficulty']) ? trim($_POST['difficulty']) : 'medium';

    if ($user_id > 0) {
        $usageMgr = new AiUsageManager($user_id);
        $checkUsage = $usageMgr->canMakeRequest();
        if ($checkUsage !== true) {
            http_response_code(403);
            echo json_encode([
                'status'  => 'error',
                'message' => $checkUsage
            ]);
            exit();
        }
    }

    $pdfBase64  = '';
    $fileName   = '';
    $totalPages = 1;

    // --- 2. Robust Multi-Format & Multi-Key File Detection ---
    $multiKey = null;
    foreach (['photos', 'image_files', 'images', 'files'] as $key) {
        if (isset($_FILES[$key]) && is_array($_FILES[$key]['name']) && count($_FILES[$key]['name']) > 0) {
            $multiKey = $key;
            break;
        }
    }

    $singleFile = null;
    if (!$multiKey) {
        foreach (['pdf_file', 'file', 'document', 'photo', 'image'] as $key) {
            if (isset($_FILES[$key]) && !empty($_FILES[$key]['tmp_name'])) {
                $singleFile = $_FILES[$key];
                break;
            }
        }
        if (!$singleFile && !empty($_FILES)) {
            // Fallback to the very first file entry in $_FILES array
            $firstKey = array_key_first($_FILES);
            if (!empty($_FILES[$firstKey]['tmp_name'])) {
                $singleFile = $_FILES[$firstKey];
            }
        }
    }

    if ($multiKey) {
        // Multi-Photo Upload Mode (Camera Snaps / Photo Studio)
        $tmpFiles = $_FILES[$multiKey]['tmp_name'];
        if (is_array($tmpFiles)) {
            $validTmpFiles = array_filter($tmpFiles, function($t) { return !empty($t) && file_exists($t); });
            $count = count($validTmpFiles);
            if ($count > 0) {
                $pdfBase64 = convertImagesToPdfBase64($validTmpFiles);
                $fileName = "Veeru_Lens_Studio_" . date('Ymd_His') . ".pdf";
                $totalPages = $count;
            }
        }
    } elseif ($singleFile) {
        // Single File Upload Mode (PDF document or single gallery photo)
        $file = $singleFile;
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
        if (empty($tmpPath) || !file_exists($tmpPath) || filesize($tmpPath) === 0) {
            throw new Exception("File is empty (0 bytes). Check your device storage permissions.");
        }

        $headerName = isset($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) && !empty($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) ? urldecode($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) : '';
        $postName = isset($_POST['custom_file_name']) && !empty($_POST['custom_file_name']) ? $_POST['custom_file_name'] : '';
        $rawName = $headerName ?: ($postName ?: ($file['name'] ?? 'study_material.pdf'));
        
        $decoded = rawurldecode($rawName);
        if (!mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = urldecode($rawName);
        }
        if (!mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = mb_convert_encoding($rawName, 'UTF-8', mb_detect_encoding($rawName));
        }
        $fileName = $decoded ?: $rawName;

        // Content-based file type detection (robust against .tmp or missing extensions)
        $fileBytes = file_get_contents($tmpPath);
        if ($fileBytes === false || strlen($fileBytes) < 20) {
            throw new Exception("Failed to read uploaded file contents.");
        }

        $isPdf = (substr($fileBytes, 0, 4) === '%PDF');
        $isImage = false;

        if (!$isPdf) {
            $imgCheck = @imagecreatefromstring($fileBytes);
            if ($imgCheck !== false) {
                imagedestroy($imgCheck);
                $isImage = true;
            }
        }

        if ($isPdf) {
            $pdfBase64 = base64_encode($fileBytes);
            unset($fileBytes);
            // Rough count of PDF pages
            $matchCount = preg_match_all('#/Type\s*/Page\b#', base64_decode(substr($pdfBase64, 0, 100000)), $m);
            $totalPages = $matchCount > 0 ? $matchCount : 1;
        } elseif ($isImage) {
            $pdfBase64 = convertImagesToPdfBase64([$tmpPath]);
            $totalPages = 1;
            if (!preg_match('#\.pdf$#i', $fileName)) {
                $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.pdf';
            }
        } else {
            throw new Exception("Unsupported file format. Please upload a valid PDF document or clear photo (JPG, PNG, WebP).");
        }
    }

    if (empty($pdfBase64)) {
        throw new Exception("No valid PDF or image file received. Please check device permissions and try again.");
    }

    // --- 3. Save Record into MySQL DB ---
    $fileHash = md5($pdfBase64);
    $fileSize = strlen($pdfBase64);

    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_hash, file_size, pdf_base64, total_pages, difficulty, status, current_step) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'Queued for AI extraction')");
    $stmt->execute([$user_id, $folder_id, $fileName, $fileHash, $fileSize, $pdfBase64, $totalPages, $difficulty]);
    $job_id = $pdo->lastInsertId();

    if ($user_id > 0 && isset($usageMgr)) {
        $usageMgr->logUsage(100);
    }

    // --- 4. Trigger Async AI Worker ---
    $secretKey = defined('WORKER_SECRET') ? WORKER_SECRET : 'veeru_ai_worker_v2_secure_ping';
    $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/backend/api'), '/\\');
    $workerUrl = "$scheme://$host$scriptDir/pdf_worker_ai.php?key=$secretKey&force_job_id=$job_id";

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
?>
