<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
// parent_id = 0 means root folders
$parent_id = isset($_GET['parent_id']) ? ($_GET['parent_id'] === 'root' ? 0 : intval($_GET['parent_id'])) : 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit();
}

try {
    // 1. Fetch Folders matching parent_id
    $sql = "SELECT folder_id, name, created_at FROM pdf_study_folders WHERE user_id = ? AND (parent_id = ? OR (? = 0 AND parent_id IS NULL)) ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $parent_id, $parent_id]);
    $folders = $stmt->fetchAll();
    
    // 2. Count jobs recursive? No, just jobs in THIS folder
    foreach ($folders as &$folder) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pdf_study_jobs WHERE folder_id = ? AND user_id = ?");
        $countStmt->execute([$folder['folder_id'], $user_id]);
        $folder['job_count'] = (int)$countStmt->fetchColumn();
    }
    
    echo json_encode(['status' => 'success', 'data' => $folders]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
