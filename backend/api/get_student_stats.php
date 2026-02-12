<?php
/**
 * Get Student Stats API
 * Fetches Gamification details (XP, Leaderboard Rank, Streaks)
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id == 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit();
}

try {
    // 1. Get Personal Stats
    $stmt = $pdo->prepare("SELECT * FROM study_streaks WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    if (!$stats) {
        // Initialize if not exists
        $pdo->prepare("INSERT INTO study_streaks (user_id) VALUES (?)")->execute([$user_id]);
        $stats = ['current_streak' => 0, 'total_xp' => 0, 'level' => 1];
    }

    // 2. Get Weekly Activity (Last 7 days completed tasks)
    $stmt = $pdo->prepare("SELECT task_date, COUNT(*) as count, SUM(xp_reward) as xp 
                          FROM study_tasks 
                          WHERE user_id = ? AND status = 'completed' AND task_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                          GROUP BY task_date");
    $stmt->execute([$user_id]);
    $activity = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'stats' => $stats,
            'activity' => $activity
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
