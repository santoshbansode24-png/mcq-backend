<?php
require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Support single class_id or multiple class_ids
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;
$class_ids = isset($_GET['class_ids']) ? $_GET['class_ids'] : null;

if (!$class_id && !$class_ids) {
    sendResponse('error', 'class_id or class_ids is required', null, 400);
}

try {
    if ($class_ids) {
        $ids_array = array_map('intval', explode(',', $class_ids));
        $inQuery = implode(',', array_fill(0, count($ids_array), '?'));
        
        $query = "
            SELECT 
                cu.id as notification_id,
                cu.teacher_id,
                cu.class_id,
                cu.title,
                cu.message,
                cu.update_type,
                cu.payload,
                cu.created_at,
                u.name as teacher_name 
            FROM class_updates cu
            JOIN users u ON cu.teacher_id = u.user_id
            WHERE cu.class_id IN ($inQuery)
            ORDER BY cu.created_at DESC
            LIMIT 100
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute($ids_array);
    } else {
        $query = "
            SELECT 
                cu.id as notification_id,
                cu.teacher_id,
                cu.class_id,
                cu.title,
                cu.message,
                cu.update_type,
                cu.payload,
                cu.created_at,
                u.name as teacher_name 
            FROM class_updates cu
            JOIN users u ON cu.teacher_id = u.user_id
            WHERE cu.class_id = ?
            ORDER BY cu.created_at DESC
            LIMIT 50
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$class_id]);
    }
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON payload
    foreach ($notifications as &$n) {
        if ($n['payload']) {
            $n['payload'] = json_decode($n['payload'], true);
        }
    }
    
    sendResponse('success', 'Notifications fetched successfully', $notifications, 200);
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
