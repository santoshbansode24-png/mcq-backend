<?php
require_once '../config/db.php';
require_once 'cors_middleware.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Support single class_id or multiple class_ids, plus optional student_id/user_id resolution
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;
$class_ids = isset($_GET['class_ids']) ? $_GET['class_ids'] : null;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0);

if (!$class_id && !$class_ids && $student_id <= 0) {
    sendResponse('error', 'class_id, class_ids, or student_id is required', null, 400);
}

try {
    // Resolve classroom IDs (Option A Safe server-side translation safeguard - Optimized 1-query lookup)
    $joined_ids = [];
    if ($class_ids) {
        $joined_ids = array_map('intval', explode(',', $class_ids));
    } elseif ($class_id) {
        $joined_ids = [intval($class_id)];
    }

    // Future-proof / Optimized: If student_id is provided, automatically fetch and merge their joined classrooms
    if ($student_id > 0) {
        $stmt_mapping = $pdo->prepare("SELECT class_id FROM student_class_mapping WHERE student_id = ?");
        $stmt_mapping->execute([$student_id]);
        $mapped_class_ids = $stmt_mapping->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($mapped_class_ids)) {
            $joined_ids = array_merge($joined_ids, array_map('intval', $mapped_class_ids));
        }
    }

    $joined_ids = array_unique(array_map('intval', $joined_ids));

    // Resolve standards (class_levels) for legacy notifications support
    $class_levels = [];
    if (!empty($joined_ids)) {
        $placeholders = implode(',', array_fill(0, count($joined_ids), '?'));
        $stmt_resolve = $pdo->prepare("
            SELECT DISTINCT class_level 
            FROM classrooms 
            WHERE class_id IN ($placeholders)
        ");
        $stmt_resolve->execute($joined_ids);
        $class_levels = $stmt_resolve->fetchAll(PDO::FETCH_COLUMN);
    }

    // Fallback if no classrooms found
    if (empty($class_levels)) {
        $class_levels = $joined_ids;
    }
    $class_levels = array_unique(array_map('intval', $class_levels));

    $inQueryClassrooms = implode(',', array_fill(0, count($joined_ids), '?'));
    $inQueryStandards = implode(',', array_fill(0, count($class_levels), '?'));

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
        WHERE cu.class_id IN ($inQueryClassrooms)
          AND cu.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
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
        WHERE n.class_id IN ($inQueryStandards)
          AND n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        ORDER BY created_at DESC
        LIMIT 100
    ";
    
    // Merge parameters in order: first classrooms, then standards
    $params = array_merge($joined_ids, $class_levels);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON payload server-side (saves client from double-parsing)
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
