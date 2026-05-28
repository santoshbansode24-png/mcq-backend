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
    $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
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
    $classStmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_id = ?");
    $classStmt->execute([$class_id]);
    if (!$classStmt->fetch()) {
        sendResponse('error', 'Invalid class ID', null, 400);
    }

    $attachment_url = null;

    // Handle file upload if present
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/materials/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileInfo = pathinfo($_FILES['file']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($ext, $allowedExts)) {
            sendResponse('error', 'Invalid file type. Allowed: PDF, DOC, JPG, PNG', null, 400);
        }

        // Generate unique filename
        $fileName = 'material_' . time() . '_' . uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            $attachment_url = 'uploads/materials/' . $fileName;
        } else {
            sendResponse('error', 'Failed to save uploaded file', null, 500);
        }
    }

    // Insert into class_updates
    $payload = $attachment_url ? json_encode(['attachment_url' => $attachment_url]) : json_encode([]);
    $stmt = $pdo->prepare("
        INSERT INTO class_updates (class_id, teacher_id, school_name, title, message, payload, update_type, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$class_id, $teacher_id, $school_name, $title, $message, $payload, $update_type]);
    
    sendResponse('success', 'Material uploaded successfully', ['id' => $pdo->lastInsertId()], 201);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
} catch (Throwable $e) {
    sendResponse('error', 'Server error occurred', ['error' => $e->getMessage()], 500);
}
?>
