<?php
require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

if (!isset($_GET['class_id'])) {
    sendResponse('error', 'Class ID is required', null, 400);
}

$class_id = $_GET['class_id'];

try {
    // Fetch from class_updates table (Teacher materials and announcements)
    $stmt = $pdo->prepare("
        SELECT 
            cu.id as notification_id,
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
    ");
    
    $stmt->execute([$class_id]);
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
