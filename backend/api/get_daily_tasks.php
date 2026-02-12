<?php
/**
 * Get Daily Tasks API
 * Fetches the daily missions for the study planner
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if ($user_id == 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit();
}

try {
    // 1. Get Tasks for the specific date
    $stmt = $pdo->prepare("SELECT * FROM study_tasks 
                          WHERE user_id = :uid AND task_date = :date 
                          ORDER BY status DESC, task_id ASC");
    $stmt->execute([':uid' => $user_id, ':date' => $date]);
    $tasks = $stmt->fetchAll();

    // 2. Get Streak Info
    $stmtRank = $pdo->prepare("SELECT current_streak, total_xp, level FROM study_streaks WHERE user_id = :uid");
    $stmtRank->execute([':uid' => $user_id]);
    $stats = $stmtRank->fetch();
    
    if (!$stats) {
        $stats = ['current_streak' => 0, 'total_xp' => 0, 'level' => 1];
    }

    // 3. Calculate Progress for Today
    $total_tasks = count($tasks);
    $completed_tasks = 0;
    foreach ($tasks as $task) {
        if ($task['status'] === 'completed') {
            $completed_tasks++;
        }
    }
    
    $progress_percentage = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

    echo json_encode([
        'status' => 'success',
        'data' => [
            'date' => $date,
            'tasks' => $tasks,
            'stats' => $stats,
            'progress' => [
                'total' => $total_tasks,
                'completed' => $completed_tasks,
                'percentage' => $progress_percentage
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
