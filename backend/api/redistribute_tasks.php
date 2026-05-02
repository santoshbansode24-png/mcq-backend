<?php
require_once 'cors_middleware.php';
/**
 * Redistribute Missed Tasks API
 * Automatically moves pending tasks from past days to future days
 * ensuring an achievable workload.
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    if (isset($_POST['user_id'])) {
        $input = $_POST;
    } else {
        $input = json_decode($_POST['data'] ?? '{}', true);
    }
}

if (!isset($input['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit();
}

$user_id = intval($input['user_id']);

try {
    $pdo->beginTransaction();

    // 1. Get the study plan details
    $stmt_plan = $pdo->prepare("SELECT plan_id, target_date FROM study_plans WHERE user_id = ?");
    $stmt_plan->execute([$user_id]);
    $plan = $stmt_plan->fetch();

    if (!$plan) {
        throw new Exception("Study plan not found.");
    }

    $plan_id = $plan['plan_id'];
    $target_date = $plan['target_date'];

    // 2. Identify missed tasks (pending AND date is in the past)
    $stmt_missed = $pdo->prepare("SELECT task_id FROM study_tasks 
                                WHERE user_id = ? 
                                AND status = 'pending' 
                                AND task_date < CURRENT_DATE
                                ORDER BY task_date ASC");
    $stmt_missed->execute([$user_id]);
    $missed_tasks = $stmt_missed->fetchAll(PDO::FETCH_COLUMN);

    if (empty($missed_tasks)) {
        $pdo->rollBack();
        echo json_encode(['status' => 'success', 'message' => 'No missed tasks found.', 'count' => 0]);
        exit();
    }

    // 3. Calculate remaining days
    $today = new DateTime();
    $exam = new DateTime($target_date);
    
    // Total days from today until exam (inclusive of today)
    $remaining_days = $exam->diff($today)->days + 1;
    $remaining_days = max(1, $remaining_days); // Ensure at least 1 day

    // 4. Proportional Redistribution
    // We iterate through missed tasks and assign them to future dates
    $count = count($missed_tasks);
    $tasks_per_day = ceil($count / $remaining_days);

    $current_task_idx = 0;
    $updates_by_date = [];

    for ($d = 0; $d < $remaining_days; $d++) {
        $new_date = date('Y-m-d', strtotime("+$d days", $today->getTimestamp()));
        $date_task_ids = [];
        
        for ($t = 0; $t < $tasks_per_day; $t++) {
            if ($current_task_idx >= $count) break;
            $date_task_ids[] = $missed_tasks[$current_task_idx];
            $current_task_idx++;
        }

        if (!empty($date_task_ids)) {
            $updates_by_date[$new_date] = $date_task_ids;
        }
        if ($current_task_idx >= $count) break;
    }

    // Execute bulk updates per date
    foreach ($updates_by_date as $new_date => $task_ids) {
        $in_clause = implode(',', array_map('intval', $task_ids));
        $stmt_update = $pdo->prepare("UPDATE study_tasks SET task_date = ? WHERE task_id IN ($in_clause)");
        $stmt_update->execute([$new_date]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success', 
        'message' => "Successfully redistributed $count missed tasks across $remaining_days days.",
        'count' => $count
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
