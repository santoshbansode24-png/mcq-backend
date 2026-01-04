<?php
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    sendResponse('error', 'Invalid user ID', null, 400);
}

try {
    // Get all badges, marking earned ones
    $stmt = $pdo->prepare("
        SELECT b.*, 
               CASE WHEN ub.earned_at IS NOT NULL THEN 1 ELSE 0 END as earned,
               ub.earned_at
        FROM badges b
        LEFT JOIN user_badges ub ON b.badge_id = ub.badge_id AND ub.user_id = ?
        ORDER BY b.badge_id
    ");
    $stmt->execute([$user_id]);
    $badges = $stmt->fetchAll();
    
    sendResponse('success', 'Badges fetched', $badges);
} catch (PDOException $e) {
    sendResponse('error', 'Database error', ['error' => $e->getMessage()], 500);
}
?>
