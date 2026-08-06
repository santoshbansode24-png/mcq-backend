<?php
header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';

if (!$user_id || !$folder_id || !$name) {
    echo json_encode(['status' => 'error', 'message' => 'User ID, Folder ID, and new Folder Name are required']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE pdf_study_folders SET name = ? WHERE folder_id = ? AND user_id = ?");
    $stmt->execute([$name, $folder_id, $user_id]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Folder renamed successfully'
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
