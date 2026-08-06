<?php
header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_name = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';

    if (!$user_id || !$id || !$new_name || !in_array($type, ['folder', 'file'])) {
        throw new Exception("Missing or invalid required parameters");
    }

    if ($type === 'folder') {
        $stmt = $pdo->prepare("UPDATE pdf_folders SET name = ? WHERE folder_id = ? AND user_id = ?");
        $stmt->execute([$new_name, $id, $user_id]);
    } else if ($type === 'file') {
        $stmt = $pdo->prepare("UPDATE pdf_study_jobs SET file_name = ? WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$new_name, $id, $user_id]);
    }

    echo json_encode(['status' => 'success', 'message' => "Successfully renamed $type"]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
