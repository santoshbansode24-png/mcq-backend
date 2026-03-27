<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? 0;
    $folder_id = $_POST['folder_id'] ?? 0;

    if (!$user_id || !$folder_id) {
        die(json_encode(['status' => 'error', 'message' => 'Missing parameters']));
    }

    try {
        // Simple recursive delete - start with children jobs
        $pdo->prepare("DELETE FROM pdf_study_jobs WHERE folder_id = ? AND user_id = ?")->execute([$folder_id, $user_id]);
        
        // Also delete subfolders (one level deep for safety, or full recursion)
        $pdo->prepare("DELETE FROM pdf_study_folders WHERE parent_id = ? AND user_id = ?")->execute([$folder_id, $user_id]);

        // Delete the folder itself
        $stmt = $pdo->prepare("DELETE FROM pdf_study_folders WHERE folder_id = ? AND user_id = ?");
        $stmt->execute([$folder_id, $user_id]);

        echo json_encode(['status' => 'success', 'message' => 'Folder and contents deleted']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
