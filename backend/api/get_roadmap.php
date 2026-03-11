<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit();
}

try {
    // Fetch all future tasks for this user
    $sql = "SELECT task_id, task_date, subject, title, task_type, duration_minutes, status, xp_reward, chapter_id
            FROM study_tasks 
            WHERE user_id = ? AND task_date >= CURRENT_DATE 
            ORDER BY task_date ASC, task_id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by date
    $roadmap = [];
    foreach ($tasks as $task) {
        $date = $task['task_date'];
        if (!isset($roadmap[$date])) {
            $roadmap[$date] = [
                'date' => $date,
                'display_date' => date('D, M j', strtotime($date)),
                'is_today' => ($date == date('Y-m-d')),
                'tasks' => []
            ];
        }
        $roadmap[$date]['tasks'][] = $task;
    }

    echo json_encode([
        'status' => 'success',
        'data' => array_values($roadmap)
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
