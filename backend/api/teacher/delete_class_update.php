<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests allowed', null, 405);
}

$input = getJsonInput();
$update_id = isset($input['update_id']) ? intval($input['update_id']) : 0;
$teacher_id = isset($input['teacher_id']) ? intval($input['teacher_id']) : 0;

if ($update_id <= 0 || $teacher_id <= 0) {
    sendResponse('error', 'Update ID and Teacher ID are required.', null, 400);
}

try {
    // Verify the update exists and belongs to a class this teacher owns
    $checkStmt = $pdo->prepare("
        SELECT cu.id, cu.class_id, cu.teacher_id, c.teacher_id as owner_id 
        FROM class_updates cu
        JOIN classrooms c ON cu.class_id = c.class_id
        WHERE cu.id = ?
    ");
    $checkStmt->execute([$update_id]);
    $update = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$update) {
        sendResponse('error', 'Update not found.', null, 404);
    }

    // Allow deletion if the teacher created the update OR owns the class
    if ($update['teacher_id'] != $teacher_id && $update['owner_id'] != $teacher_id) {
        sendResponse('error', 'Unauthorized to delete this update.', null, 403);
    }

    // Delete the update
    $delStmt = $pdo->prepare("DELETE FROM class_updates WHERE id = ?");
    $delStmt->execute([$update_id]);

    sendResponse('success', 'Update deleted successfully.', null, 200);

} catch (PDOException $e) {
    error_log("Delete Update Error: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
