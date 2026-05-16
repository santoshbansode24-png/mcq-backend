<?php
/**
 * Delete Account API
 * Veeru
 * 
 * Required to comply with Google Play Store data deletion policy.
 * Permanently removes user and all their associated progress data.
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow POST requests for deletion (since it's a destructive action)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed.', null, 405);
}

// Get JSON input
$input = getJsonInput();
$user_id = isset($input['user_id']) ? intval($input['user_id']) : null;

if (!$user_id) {
    sendResponse('error', 'User ID is required', null, 400);
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // 0. Garbage Collection: Delete physical PDFs and AI Content
    $stmt_files = $pdo->prepare("SELECT file_path FROM pdf_study_jobs WHERE user_id = ? AND file_path IS NOT NULL AND file_path != ''");
    $stmt_files->execute([$user_id]);
    while ($fileRow = $stmt_files->fetch()) {
        $filePath = $fileRow['file_path'];
        if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
            $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . $filePath;
        }
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
    
    // Clean up Veeru Lens Database Records
    try {
        $pdo->prepare("DELETE FROM pdf_study_content WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM pdf_study_jobs WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM pdf_study_folders WHERE user_id = ?")->execute([$user_id]);
    } catch (Exception $e) {}

    // 1. Delete progress data
    $stmt1 = $pdo->prepare("DELETE FROM content_progress WHERE user_id = ?");
    $stmt1->execute([$user_id]);

    // 2. Delete study plans (if table exists)
    try {
        $stmt2 = $pdo->prepare("DELETE FROM study_plans WHERE user_id = ?");
        $stmt2->execute([$user_id]);
    } catch (Exception $e) {
        // Table might not exist yet, ignore
    }

    // 3. Delete MCQ attempts
    try {
        $stmt3 = $pdo->prepare("DELETE FROM mcq_attempts WHERE user_id = ?");
        $stmt3->execute([$user_id]);
    } catch (Exception $e) {
        // Table might not exist yet, ignore
    }

    // 4. Finally delete the user account
    $stmtUser = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND user_type = 'student'");
    $stmtUser->execute([$user_id]);

    if ($stmtUser->rowCount() === 0) {
        $pdo->rollBack();
        sendResponse('error', 'User not found or calculation failed.', null, 404);
    }

    $pdo->commit();
    
    // Log deletion for admin tracking (optional)
    file_put_contents('../deletion_logs.txt', date('Y-m-d H:i:s') . " - User ID $user_id deleted permanently.\n", FILE_APPEND);

    sendResponse('success', 'Account and all associated data deleted successfully.');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
