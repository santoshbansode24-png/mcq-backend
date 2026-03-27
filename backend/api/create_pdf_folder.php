<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;

if (!$user_id || !$name) {
    echo json_encode(['status' => 'error', 'message' => 'User ID and Folder Name are required']);
    exit();
}

try {
    // If parent_id is 0, we can set it to NULL if we want root
    $p_id = ($parent_id === 0) ? null : $parent_id;
    
    $stmt = $pdo->prepare("INSERT INTO pdf_study_folders (user_id, name, parent_id) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $name, $p_id]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Folder created successfully',
        'folder_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
