<?php
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    sendResponse('error', 'No file uploaded or upload error', null, 400);
}

// Get user ID
if (!isset($_POST['user_id'])) {
    sendResponse('error', 'User ID is required', null, 400);
}

$userId = $_POST['user_id'];
$file = $_FILES['profile_picture'];

// Validate file size (Max 5MB)
const MAX_FILE_SIZE = 5 * 1024 * 1024;
if ($file['size'] > MAX_FILE_SIZE || $file['size'] == 0) {
    sendResponse('error', 'File size exceeds the 5MB limit or is empty.', null, 400);
}

// Strict MIME type validation using Fileinfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif'
];

if (!array_key_exists($mimeType, $allowedMimeTypes)) {
    sendResponse('error', 'Invalid file type. Only real JPG, PNG, and GIF images are allowed.', null, 400);
}

// Create uploads directory if it doesn't exist
$uploadDir = '../../uploads/profile_pictures/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename securely
$extension = $allowedMimeTypes[$mimeType]; // Use trusted extension based on MIME type
$filename = 'user_' . $userId . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
$targetPath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Update database
    try {
        // Get old profile picture to delete
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $oldPic = $stmt->fetchColumn();
        
        // Update with new picture URL (relative path for API access)
        $dbPath = 'uploads/profile_pictures/' . $filename;
        $updateStmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
        $updateStmt->execute([$dbPath, $userId]);
        
        // Delete old file if it exists
        if ($oldPic && file_exists('../../' . $oldPic)) {
            unlink('../../' . $oldPic);
        }
        
        sendResponse('success', 'Profile picture uploaded successfully', ['profile_picture' => $dbPath], 200);
    } catch (PDOException $e) {
        sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
    }
} else {
    sendResponse('error', 'Failed to save uploaded file', null, 500);
}
?>
