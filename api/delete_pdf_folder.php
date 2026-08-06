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
        // 1. Safe Eviction: Shift orphaned PDFs back to root (folder_id = NULL) to prevent data loss
        $pdo->prepare("UPDATE pdf_study_jobs SET folder_id = NULL WHERE folder_id = ? AND user_id = ?")->execute([$folder_id, $user_id]);
        
        // 2. Also shift subfolders up to root instead of deleting them (safety)
        $pdo->prepare("UPDATE pdf_study_folders SET parent_id = NULL WHERE parent_id = ? AND user_id = ?")->execute([$folder_id, $user_id]);

        // 3. Delete the folder itself
        $stmt = $pdo->prepare("DELETE FROM pdf_study_folders WHERE folder_id = ? AND user_id = ?");
        $stmt->execute([$folder_id, $user_id]);

        echo json_encode(['status' => 'success', 'message' => 'Folder permanently deleted. Safe mode active: nested PDFs moved to Main Vault.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
