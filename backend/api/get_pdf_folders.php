<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$parent_id = isset($_GET['parent_id']) ? ($_GET['parent_id'] === 'root' ? 0 : intval($_GET['parent_id'])) : 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit();
}

try {
    // 1. Fetch folders
    $sql = "SELECT folder_id, name, created_at FROM pdf_study_folders 
            WHERE user_id = ? AND (parent_id = ? OR (? = 0 AND parent_id IS NULL)) 
            ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $parent_id, $parent_id]);
    $folders = $stmt->fetchAll();

    // 2. OPTIMIZED: Get all job counts in ONE query (eliminates N+1 problem)
    $folderIds = array_column($folders, 'folder_id');
    $jobCounts = [];
    if (!empty($folderIds)) {
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        $countStmt = $pdo->prepare(
            "SELECT folder_id, COUNT(*) as cnt FROM pdf_study_jobs 
             WHERE user_id = ? AND folder_id IN ($placeholders) GROUP BY folder_id"
        );
        $countStmt->execute(array_merge([$user_id], $folderIds));
        foreach ($countStmt->fetchAll() as $row) {
            $jobCounts[$row['folder_id']] = (int)$row['cnt'];
        }
    }

    // 3. Map counts back to folders
    foreach ($folders as &$folder) {
        $folder['job_count'] = $jobCounts[$folder['folder_id']] ?? 0;
    }
    
    echo json_encode(['status' => 'success', 'data' => $folders]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
