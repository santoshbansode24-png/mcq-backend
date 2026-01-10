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
    // Fetch notifications for the class
    $stmt = $pdo->prepare("
        SELECT n.*, u.name as teacher_name 
        FROM notifications n
        JOIN users u ON n.teacher_id = u.user_id
        WHERE n.class_id = ?
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    
    $stmt->execute([$class_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse('success', 'Notifications fetched successfully', $notifications, 200);
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
