<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$user_id      = isset($_GET['user_id'])      ? intval($_GET['user_id']) : 0;
$include_past = isset($_GET['include_past']) ? intval($_GET['include_past']) : 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit();
}

try {
    // include_past=1 → include yesterday's tasks too (for smart notification context)
    if ($include_past) {
        $date_filter = "task_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)";
    } else {
        $date_filter = "task_date >= CURRENT_DATE";
    }

    $sql = "SELECT task_id, task_date, subject, title, task_type, duration_minutes,
                   status, xp_reward, chapter_id, chapter_ids
            FROM study_tasks
            WHERE user_id = ? AND $date_filter
            ORDER BY task_date ASC, task_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by date
    $roadmap = [];
    $today   = date('Y-m-d');
    foreach ($tasks as $task) {
        $date = $task['task_date'];
        if (!isset($roadmap[$date])) {
            $roadmap[$date] = [
                'date'         => $date,
                'display_date' => date('D, M j', strtotime($date)),
                'is_today'     => ($date === $today),
                'is_yesterday' => ($date === date('Y-m-d', strtotime('-1 day'))),
                'tasks'        => []
            ];
        }
        $roadmap[$date]['tasks'][] = $task;
    }

    // Compute per-day completion stats (useful for front-end progress display)
    foreach ($roadmap as &$day) {
        $total     = count($day['tasks']);
        $completed = count(array_filter($day['tasks'], fn($t) => $t['status'] === 'completed'));
        $day['total_tasks']     = $total;
        $day['completed_tasks'] = $completed;
        $day['completion_pct']  = $total > 0 ? round(($completed / $total) * 100) : 0;
    }

    echo json_encode([
        'status' => 'success',
        'data'   => array_values($roadmap)
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
