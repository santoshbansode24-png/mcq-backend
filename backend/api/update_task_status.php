<?php
/**
 * Update Task Status API
 * Marks a task as completed or skipped and awards XP
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['task_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit();
}

$user_id = $input['user_id'];
$task_id = $input['task_id'];
$status = $input['status']; // 'completed', 'in_progress', 'skipped'

try {
    $pdo->beginTransaction();

    // 1. Verify Task ownership
    $stmt = $pdo->prepare("SELECT task_id, status, xp_reward, task_date FROM study_tasks WHERE task_id = :tid AND user_id = :uid");
    $stmt->execute([':tid' => $task_id, ':uid' => $user_id]);
    $task = $stmt->fetch();

    if (!$task) {
        throw new Exception("Task not found");
    }

    // Only award XP if moving from non-completed to completed
    $xp_to_add = 0;
    if ($status === 'completed' && $task['status'] !== 'completed') {
        $xp_to_add = $task['xp_reward'];
    }

    // 2. Update Task Status
    $stmt = $pdo->prepare("UPDATE study_tasks SET status = :status, completed_at = NOW() WHERE task_id = :tid");
    $stmt->execute([':status' => $status, ':tid' => $task_id]);

    // 3. Update User Stats (XP & Streak)
    if ($xp_to_add > 0) {
        // Update XP
        $stmt = $pdo->prepare("UPDATE study_streaks SET total_xp = total_xp + :xp, last_active_date = :today WHERE user_id = :uid");
        $stmt->execute([
            ':xp' => $xp_to_add, 
            ':today' => date('Y-m-d'), 
            ':uid' => $user_id
        ]);

        // Logic to update Streak (Simple version: if last active was yesterday, increment. If today, do nothing. If older, reset.)
        // For simplicity in this step, we just ensure the row exists. A separate cron or login check usually handles refined streak logic.
        // We will assume "daily login" updates the streak counter elsewhere, or we can add a basic check here.
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Task updated',
        'xp_earned' => $xp_to_add
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
