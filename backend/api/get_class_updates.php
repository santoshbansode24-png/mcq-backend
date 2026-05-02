<?php
/**
 * Get Class Updates API (Student View)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

$school_name = isset($_GET['school_name']) ? sanitizeInput($_GET['school_name']) : '';
$class_id = isset($_GET['class_id']) ? filter_var($_GET['class_id'], FILTER_VALIDATE_INT) : 0;

if (empty($school_name) || $class_id <= 0) {
    sendResponse('error', 'School name and Class ID are required', null, 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT cu.update_id, cu.update_type, cu.title, cu.message, cu.payload, cu.created_at, t.name as teacher_name
        FROM class_updates cu
        JOIN teachers t ON cu.teacher_id = t.id
        WHERE cu.school_name = ? AND cu.class_id = ?
        ORDER BY cu.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$school_name, $class_id]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON payloads
    foreach ($updates as &$update) {
        if (!empty($update['payload'])) {
            $update['payload'] = json_decode($update['payload'], true);
        }
    }
    unset($update);

    sendResponse('success', 'Updates fetched successfully', $updates, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
