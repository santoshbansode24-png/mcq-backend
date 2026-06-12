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
    // Resolve classroom IDs (Option A Safe server-side translation safeguard - Optimized 1-query lookup)
    $input_ids = [];
    if ($class_ids) {
        $input_ids = array_map('intval', explode(',', $class_ids));
    } elseif ($class_id) {
        $input_ids = [intval($class_id)];
    }

    $target_classroom_ids = [];
    if (!empty($input_ids)) {
        $placeholders = implode(',', array_fill(0, count($input_ids), '?'));
        // Resolve both classroom IDs and generic class levels in a single query
        $stmt_resolve = $pdo->prepare("
            SELECT class_id 
            FROM classrooms 
            WHERE class_id IN ($placeholders) OR class_level IN ($placeholders)
        ");
        $params = array_merge($input_ids, $input_ids);
        $stmt_resolve->execute($params);
        $target_classroom_ids = $stmt_resolve->fetchAll(PDO::FETCH_COLUMN);
    }

    // Fallback if no classrooms found
    if (empty($target_classroom_ids)) {
        $target_classroom_ids = $input_ids;
    }

    $target_classroom_ids = array_unique(array_map('intval', $target_classroom_ids));
    $inQuery = implode(',', array_fill(0, count($target_classroom_ids), '?'));

    $query = "
        SELECT 
            cu.id as notification_id,
            cu.teacher_id,
            cu.class_id,
            cu.school_name,
            cu.title,
            cu.message,
            cu.update_type,
            cu.payload,
            cu.created_at,
            u.name as teacher_name 
        FROM class_updates cu
        JOIN users u ON cu.teacher_id = u.user_id
        WHERE cu.class_id IN ($inQuery)
        
        UNION ALL
        
        SELECT 
            n.notification_id,
            n.teacher_id,
            n.class_id,
            NULL as school_name,
            n.title,
            n.message,
            'announcement' as update_type,
            NULL as payload,
            n.created_at,
            u.name as teacher_name 
        FROM notifications n
        JOIN users u ON n.teacher_id = u.user_id
        WHERE n.class_id IN ($inQuery)
        
        ORDER BY created_at DESC
        LIMIT 100
    ";
    
    // Merge parameters for the UNION (needs two sets of class_ids)
    $params = array_merge($target_classroom_ids, $target_classroom_ids);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON payload
    foreach ($notifications as $key => $n) {
        if (!empty($n['payload'])) {
            $notifications[$key]['payload'] = json_decode($n['payload'], true);
        }
    }
    
    sendResponse('success', 'Notifications fetched successfully', $notifications, 200);
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
