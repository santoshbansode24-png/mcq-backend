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

        $w = 612;
        $h = 792;
        $sizeInfo = @getimagesize($tmpPath);
        if ($sizeInfo && !empty($sizeInfo[0]) && !empty($sizeInfo[1])) {
            $w = intval($sizeInfo[0]);
            $h = intval($sizeInfo[1]);
        }

        $jpgBytes = null;
        $isJpegMagic = (substr($imgData, 0, 3) === "\xFF\xD8\xFF");

        if ($isJpegMagic) {
            // Direct JPEG stream embedding into PDF (/DCTDecode) - Zero GD requirement, high speed & low RAM
            $jpgBytes = $imgData;
        } elseif (function_exists('imagecreatefromstring')) {
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
                $w = imagesx($im) ?: $w;
                $h = imagesy($im) ?: $h;
                ob_start();
                imagejpeg($im, null, 85);
                $jpgBytes = ob_get_clean();
                imagedestroy($im);
            }
        }

        if ($jpgBytes) {
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
    // --- 1. Parse JSON or POST Parameters ---
    $rawInput  = file_get_contents('php://input');
    $jsonInput = (!empty($rawInput) && strpos(trim($rawInput), '{') === 0) ? @json_decode($rawInput, true) : [];
    if (!is_array($jsonInput)) $jsonInput = [];

    $user_id    = isset($_POST['user_id']) ? intval($_POST['user_id']) : (isset($jsonInput['user_id']) ? intval($jsonInput['user_id']) : 0);
    $folder_id  = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : (isset($jsonInput['folder_id']) ? intval($jsonInput['folder_id']) : null);
    $difficulty = isset($_POST['difficulty']) ? trim($_POST['difficulty']) : (isset($jsonInput['difficulty']) ? trim($jsonInput['difficulty']) : 'medium');

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

    // --- 2. Universal File & Base64 Harvester ---
    $collectedTmpFiles = [];
    $rawBase64Inputs   = [];

    // Custom filename override
    $headerName = isset($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) && !empty($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) ? urldecode($_SERVER['HTTP_X_CUSTOM_FILE_NAME']) : '';
    $postName   = isset($_POST['custom_file_name']) && !empty($_POST['custom_file_name']) ? $_POST['custom_file_name'] : (isset($jsonInput['custom_file_name']) ? $jsonInput['custom_file_name'] : '');
    if (!empty($headerName)) $fileName = $headerName;
    elseif (!empty($postName)) $fileName = $postName;

    // Harvester A: Harvest files from $_FILES (supports any key, single or array)
    if (!empty($_FILES)) {
        foreach ($_FILES as $key => $fileInfo) {
            if (empty($fileInfo['name']) && empty($fileInfo['tmp_name'])) continue;

            if (is_array($fileInfo['tmp_name'])) {
                // Multi-file array format (e.g. photos[], files[])
                foreach ($fileInfo['tmp_name'] as $idx => $tmpPath) {
                    $err = is_array($fileInfo['error']) ? ($fileInfo['error'][$idx] ?? UPLOAD_ERR_OK) : UPLOAD_ERR_OK;
                    if ($err === UPLOAD_ERR_OK && !empty($tmpPath) && file_exists($tmpPath) && filesize($tmpPath) > 0) {
                        $collectedTmpFiles[] = $tmpPath;
                        if (empty($fileName) && is_array($fileInfo['name']) && !empty($fileInfo['name'][$idx])) {
                            $fileName = $fileInfo['name'][$idx];
                        }
                    }
                }
            } else {
                // Single file format (e.g. photo, file, pdf_file)
                $err = $fileInfo['error'] ?? UPLOAD_ERR_OK;
                if ($err === UPLOAD_ERR_OK && !empty($fileInfo['tmp_name']) && file_exists($fileInfo['tmp_name']) && filesize($fileInfo['tmp_name']) > 0) {
                    $collectedTmpFiles[] = $fileInfo['tmp_name'];
                    if (empty($fileName) && !empty($fileInfo['name'])) {
                        $fileName = $fileInfo['name'];
                    }
                }
            }
        }
    }

    // Harvester B: Harvest Base64 from JSON / POST body if $_FILES produced no valid files
    if (empty($collectedTmpFiles)) {
        $b64Keys = ['pdf_base64', 'image_base64', 'photo_base64', 'base64', 'images_base64', 'photos_base64'];
        foreach ($b64Keys as $bk) {
            $val = $_POST[$bk] ?? ($jsonInput[$bk] ?? null);
            if (!empty($val)) {
                if (is_array($val)) {
                    foreach ($val as $bItem) {
                        if (is_string($bItem) && strlen(trim($bItem)) > 50) {
                            $rawBase64Inputs[] = trim($bItem);
                        }
                    }
                } elseif (is_string($val) && strlen(trim($val)) > 50) {
                    $rawBase64Inputs[] = trim($val);
                }
            }
        }
    }

    // --- 3. Process Harvested Content into Standard Base64 PDF ---
    if (!empty($collectedTmpFiles)) {
        if (count($collectedTmpFiles) === 1) {
            $firstBytes = @file_get_contents($collectedTmpFiles[0]);
            if ($firstBytes && substr($firstBytes, 0, 4) === '%PDF') {
                // Direct PDF upload
                $pdfBase64  = base64_encode($firstBytes);
                $matchCount = preg_match_all('#/Type\s*/Page\b#', substr($firstBytes, 0, 100000), $m);
                $totalPages = $matchCount > 0 ? $matchCount : 1;
            } else {
                // Single Image upload -> Convert to PDF
                $pdfBase64  = convertImagesToPdfBase64($collectedTmpFiles);
                $totalPages = 1;
                if (!empty($fileName) && !preg_match('#\.pdf$#i', $fileName)) {
                    $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.pdf';
                }
            }
        } else {
            // Multi-Photo Snaps -> Convert all to multi-page PDF
            $pdfBase64  = convertImagesToPdfBase64($collectedTmpFiles);
            $totalPages = count($collectedTmpFiles);
            $fileName   = "Veeru_Lens_Studio_" . date('Ymd_His') . ".pdf";
        }
    } elseif (!empty($rawBase64Inputs)) {
        // Convert Base64 inputs to temporary files if they are images
        $tempFilesCreated = [];
        $hasPdfBase64 = false;

        foreach ($rawBase64Inputs as $b64Str) {
            $cleanB64 = preg_replace('#^data:(?:image|application)/[\w\-]+;base64,#i', '', $b64Str);
            $decoded  = base64_decode($cleanB64);
            if (!$decoded || strlen($decoded) < 20) continue;

            if (substr($decoded, 0, 4) === '%PDF') {
                $pdfBase64    = base64_encode($decoded);
                $hasPdfBase64 = true;
                break;
            } else {
                $tPath = sys_get_temp_dir() . '/b64_snap_' . uniqid() . '.jpg';
                file_put_contents($tPath, $decoded);
                $tempFilesCreated[] = $tPath;
            }
        }

        if (!$hasPdfBase64 && !empty($tempFilesCreated)) {
            $pdfBase64  = convertImagesToPdfBase64($tempFilesCreated);
            $totalPages = count($tempFilesCreated);
            foreach ($tempFilesCreated as $tf) { @unlink($tf); }
        }
    }

    if (empty($pdfBase64)) {
        throw new Exception("No valid PDF or image file received. Please check device permissions and try again.");
    }

    if (empty($fileName)) {
        $fileName = "Veeru_Lens_Job_" . date('Ymd_His') . ".pdf";
    }

    // --- 4. Save Record into MySQL DB ---
    $fileHash = md5($pdfBase64);
    $fileSize = strlen($pdfBase64);

    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_hash, file_size, pdf_base64, total_pages, difficulty, status, current_step) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'Queued for AI extraction')");
    $stmt->execute([$user_id, $folder_id, $fileName, $fileHash, $fileSize, $pdfBase64, $totalPages, $difficulty]);
    $job_id = $pdo->lastInsertId();

    if ($user_id > 0 && isset($usageMgr)) {
        $usageMgr->logUsage(100);
    }

    // --- 5. Trigger Async AI Worker ---
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
        'status'    => 'success',
        'message'   => 'Study job uploaded and queued successfully.',
        'job_id'    => $job_id,
        'file_name' => $fileName
    ]);

} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
