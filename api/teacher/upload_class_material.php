<?php
/**
 * Upload Class Material API (Teacher)
 * Veeru
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

try {
    $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
    $title = isset($_POST['title']) ? sanitizeInput($_POST['title']) : '';
    $raw_message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $message = sanitizeInput($raw_message);
    $update_type = isset($_POST['update_type']) ? sanitizeInput($_POST['update_type']) : 'homework';

    if ($teacher_id <= 0 || $class_id <= 0) {
        sendResponse('error', 'Invalid teacher_id or class_id', null, 400);
    }

    // Verify teacher exists
    $teacherStmt = $pdo->prepare("SELECT user_id, school_name FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $teacherStmt->execute([$teacher_id]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher) {
        sendResponse('error', 'Invalid teacher ID', null, 403);
    }
    $school_name = $teacher['school_name'] ?? '';
    
    // Verify class exists
    $classStmt = $pdo->prepare("SELECT class_id FROM classrooms WHERE class_id = ?");
    $classStmt->execute([$class_id]);
    if (!$classStmt->fetch()) {
        sendResponse('error', 'Invalid class ID', null, 400);
    }

    $attachment_url = null;

    // Handle file upload if present
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/materials/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate file size (Max 50MB)
        $max_size = 50 * 1024 * 1024;
        if ($_FILES['file']['size'] > $max_size || $_FILES['file']['size'] == 0) {
            sendResponse('error', 'File size exceeds the 50MB limit or is empty.', null, 400);
        }

        // Strict MIME type validation using Fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png'
        ];
        
        if (!array_key_exists($mimeType, $allowedMimeTypes)) {
            sendResponse('error', 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG', null, 400);
        }

        // Generate unique filename securely
        $trusted_ext = $allowedMimeTypes[$mimeType];
        $fileName = 'material_' . bin2hex(random_bytes(16)) . '.' . $trusted_ext;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            $attachment_url = 'uploads/materials/' . $fileName;
        } else {
            $error = error_get_last();
            sendResponse('error', 'Failed to save uploaded file: ' . ($error['message'] ?? 'Unknown error'), null, 500);
        }
    }

    // Process payload
    $payloadData = [];
    if ($attachment_url) {
        $payloadData['attachment_url'] = $attachment_url;
    }

    // Extract JSON_PAYLOAD from message if present to prevent truncation in TEXT column
    if (strpos($raw_message, 'JSON_PAYLOAD:') !== false) {
        $payloadMarker = 'JSON_PAYLOAD:';
        $markerPos = strpos($raw_message, $payloadMarker);
        $jsonStart = strpos($raw_message, '{', $markerPos);
        if ($jsonStart !== false) {
            $jsonStr = substr($raw_message, $jsonStart);
            $parsedJson = json_decode($jsonStr, true);
            if ($parsedJson) {
                // Merge into payloadData
                $payloadData = array_merge($payloadData, $parsedJson);
            }
            // Clear message or set to a fallback
            $message = "New Worksheet Available";
        }
    }

    // Support direct payload array from POST (new method)
    $postPayload = isset($_POST['payload']) ? json_decode($_POST['payload'], true) : null;
    if (is_array($postPayload)) {
        $payloadData = array_merge($payloadData, $postPayload);
    }

    $payload = json_encode($payloadData);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO class_updates (class_id, teacher_id, school_name, title, message, payload, update_type, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$class_id, $teacher_id, $school_name, $title, $message, $payload, $update_type]);
        
        sendResponse('success', 'Material uploaded successfully', ['id' => $pdo->lastInsertId()], 201);
    } catch (PDOException $e) {
        // If strict mode rejects 'worksheet', fallback to 'material' instead of altering table
        if (strpos($e->getMessage(), 'update_type') !== false || strpos($e->getMessage(), 'ENUM') !== false || strpos($e->getMessage(), 'Data truncated') !== false) {
            try {
                $update_type = 'material'; // Fallback to 'material' instead of 'pdf' so it shows up in worksheets tab
                $stmt = $pdo->prepare("
                    INSERT INTO class_updates (class_id, teacher_id, school_name, title, message, payload, update_type, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$class_id, $teacher_id, $school_name, $title, $message, $payload, $update_type]);
                sendResponse('success', 'Material uploaded successfully', ['id' => $pdo->lastInsertId()], 201);
            } catch (PDOException $retryEx) {
                file_put_contents('../../debug_log.txt', "PDOException Retry: " . $retryEx->getMessage() . "\n", FILE_APPEND);
                sendResponse('error', 'Database error occurred: ' . $retryEx->getMessage(), ['error' => $retryEx->getMessage()], 500);
            }
        } else {
            file_put_contents('../../debug_log.txt', "PDOException: " . $e->getMessage() . "\n", FILE_APPEND);
            sendResponse('error', 'Database error occurred: ' . $e->getMessage(), ['error' => $e->getMessage()], 500);
        }
    }
    
} catch (Throwable $e) {
    file_put_contents('../../debug_log.txt', "Throwable: " . $e->getMessage() . "\n", FILE_APPEND);
    sendResponse('error', 'Server error occurred: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), null, 500);
}
?>
