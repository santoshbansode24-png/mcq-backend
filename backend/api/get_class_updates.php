<?php
/**
 * Get Class Updates API (Student)
 * Returns announcements, PDFs, and photos shared by the teacher.
 */
require_once '../config/db.php';
require_once 'cors_middleware.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($user_id <= 0 || $class_id <= 0) {
    sendResponse('error', 'User ID and Class ID are required.', null, 400);
}

try {
    // Fetch updates for this class
    // We filter by class_id. Optional: filter by school_name if multiple schools use the same class_id.
    $stmt = $pdo->prepare("
        SELECT 
            cu.id,
            cu.teacher_id,
            u.name as teacher_name,
            cu.update_type,
            cu.title,
            cu.message,
            cu.payload,
            cu.created_at
        FROM class_updates cu
        JOIN users u ON cu.teacher_id = u.user_id
        WHERE cu.class_id = ?
        ORDER BY cu.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$class_id]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON payload
    foreach ($updates as &$update) {
        if ($update['payload']) {
            $update['payload'] = json_decode($update['payload'], true);
        }
    }

    sendResponse('success', 'Updates retrieved successfully', $updates, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
