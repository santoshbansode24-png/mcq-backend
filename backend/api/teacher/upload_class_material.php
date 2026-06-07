<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';
require_once '../../config/push_notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests allowed', null, 405);
}

// Ensure the uploads directory exists
$uploadDir = '../../uploads/class_materials/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 1. Validate required text fields
$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
$update_type = isset($_POST['update_type']) ? $_POST['update_type'] : 'announcement';
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($teacher_id <= 0 || $class_id <= 0 || empty($title)) {
    sendResponse('error', 'Teacher ID, Class ID, and Title are required.', null, 400);
}

// Fetch teacher school name for isolation
try {
    $tStmt = $pdo->prepare("SELECT school_name FROM users WHERE user_id = ?");
    $tStmt->execute([$teacher_id]);
    $teacherInfo = $tStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacherInfo) {
        sendResponse('error', 'Invalid teacher ID', null, 404);
    }
    $school_name = $teacherInfo['school_name'] ?? 'Unknown';
} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}

$payload = isset($_POST['payload']) ? json_decode($_POST['payload'], true) : [];
if (!is_array($payload)) $payload = [];

// 2. Handle File Upload if present
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileSize = $_FILES['file']['size'];
    
    // Check size limit (e.g., 15MB)
    if ($fileSize > 15 * 1024 * 1024) {
        sendResponse('error', 'File size exceeds 15MB limit.', null, 400);
    }

    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        sendResponse('error', 'Invalid file type. Only PDF and images are allowed.', null, 400);
    }

    // Generate unique name
    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
    $destPath = $uploadDir . $newFileName;
    
    if (move_uploaded_file($fileTmpPath, $destPath)) {
        $payload['file_url'] = 'uploads/class_materials/' . $newFileName;
        $payload['file_name'] = $fileName;
        
        // Auto-correct update_type ONLY if it's currently 'announcement' or generic.
        // If teacher specifically sent 'homework' or 'worksheet', KEEP it.
        if ($update_type === 'announcement' || $update_type === 'material') {
            $update_type = ($fileExtension === 'pdf') ? 'pdf' : 'photo';
        }
    } else {
        sendResponse('error', 'Error moving the uploaded file.', null, 500);
    }
} else if ($update_type === 'pdf' || $update_type === 'photo') {
    sendResponse('error', 'File is required for ' . $update_type . ' updates.', null, 400);
}

// 3. Insert into database
try {
    $stmt = $pdo->prepare("
        INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $payloadJson = empty($payload) ? null : json_encode($payload);
    
    $stmt->execute([
        $teacher_id,
        $school_name,
        $class_id,
        $update_type,
        $title,
        $message,
        $payloadJson
    ]);
    $update_id = $pdo->lastInsertId();

    // Trigger push notification to students in this class
    sendClassPushNotifications($pdo, $class_id, "New Worksheet/Material: " . $title, $message, [
        'type' => 'worksheet',
        'update_id' => $update_id,
        'screen' => 'ClassUpdates'
    ]);
    
    sendResponse('success', 'Material uploaded successfully!', null, 200);
} catch (PDOException $e) {
    // If strict mode rejects 'worksheet', fallback to 'material' instead of altering table
    if (strpos($e->getMessage(), 'update_type') !== false || strpos($e->getMessage(), 'ENUM') !== false || strpos($e->getMessage(), 'Data truncated') !== false) {
        try {
            $update_type = 'material'; // Fallback to 'material'
            $stmt->execute([
                $teacher_id,
                $school_name,
                $class_id,
                $update_type,
                $title,
                $message,
                $payloadJson
            ]);
            $update_id = $pdo->lastInsertId();

            // Trigger push notification to students in this class
            sendClassPushNotifications($pdo, $class_id, "New Worksheet/Material: " . $title, $message, [
                'type' => 'worksheet',
                'update_id' => $update_id,
                'screen' => 'ClassUpdates'
            ]);

            sendResponse('success', 'Material uploaded successfully!', null, 200);
        } catch (PDOException $retryEx) {
            error_log("Error saving class update after retry: " . $retryEx->getMessage());
            sendResponse('error', 'Database error occurred: ' . $retryEx->getMessage(), ['error' => $retryEx->getMessage()], 500);
        }
    } else {
        error_log("Error saving class update: " . $e->getMessage());
        sendResponse('error', 'Database error occurred: ' . $e->getMessage(), ['error' => $e->getMessage()], 500);
    }
}
?>
