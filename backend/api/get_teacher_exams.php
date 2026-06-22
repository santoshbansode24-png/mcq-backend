<?php
/**
 * Get Teacher Exams and their Results
 * Fetches all exams sent by a teacher and the scores of students who took them
 */
require_once 'cors_middleware.php';

if (!isset($_GET['teacher_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing teacher_id']);
    exit();
}

$teacher_id = intval($_GET['teacher_id']);
require_once '../config/db.php';

try {
    // 1. Ensure table exists just in case they fetch before any student submits
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS class_exam_results (
            result_id INT AUTO_INCREMENT PRIMARY KEY,
            update_id INT NOT NULL,
            user_id INT NOT NULL,
            correct INT DEFAULT 0,
            incorrect INT DEFAULT 0,
            unanswered INT DEFAULT 0,
            total INT DEFAULT 0,
            time_seconds INT DEFAULT 0,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_attempt (update_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Fetch all exams sent by this teacher
    $examsStmt = $pdo->prepare("
        SELECT cu.update_id, cu.class_id, cu.title, cu.message, cu.created_at, cu.update_type, cu.payload, c.class_name
        FROM class_updates cu
        LEFT JOIN classes c ON cu.class_id = c.class_id
        WHERE cu.teacher_id = ? AND cu.update_type IN ('exam', 'live_exam')
        ORDER BY cu.created_at DESC
    ");
    $examsStmt->execute([$teacher_id]);
    $exams = $examsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($exams)) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit();
    }

    // Map each update to its target exam ID (live_exams.id for live exams, class_updates.id for regular)
    $update_to_target_id = [];
    $target_ids = [];
    foreach ($exams as $exam) {
        $uid = intval($exam['update_id']);
        $target_id = $uid;
        if ($exam['update_type'] === 'live_exam' && !empty($exam['payload'])) {
            $payload = json_decode($exam['payload'], true);
            if (isset($payload['exam_id'])) {
                $target_id = intval($payload['exam_id']);
            }
        }
        $update_to_target_id[$uid] = $target_id;
        $target_ids[] = $target_id;
    }

    $unique_target_ids = array_unique($target_ids);
    $placeholders = implode(',', array_fill(0, count($unique_target_ids), '?'));

    // 3. Fetch all results for these target IDs
    $resultsStmt = $pdo->prepare("
        SELECT r.update_id, r.correct, r.incorrect, r.unanswered, r.total, r.time_seconds, r.submitted_at, u.name as student_name, u.user_id
        FROM class_exam_results r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.update_id IN ($placeholders)
        ORDER BY r.correct DESC, r.time_seconds ASC
    ");
    $resultsStmt->execute(array_values($unique_target_ids));
    $all_results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Group results by target update_id
    $grouped_results = [];
    foreach ($all_results as $row) {
        $tid = intval($row['update_id']);
        if (!isset($grouped_results[$tid])) {
            $grouped_results[$tid] = [];
        }
        $grouped_results[$tid][] = [
            'student_name' => $row['student_name'],
            'user_id' => $row['user_id'],
            'correct' => $row['correct'],
            'total' => $row['total'],
            'percentage' => $row['total'] > 0 ? round(($row['correct'] / $row['total']) * 100) : 0,
            'time_seconds' => $row['time_seconds'],
            'submitted_at' => $row['submitted_at']
        ];
    }

    // 5. Attach results to exams
    foreach ($exams as &$exam) {
        $uid = intval($exam['update_id']);
        $target_id = $update_to_target_id[$uid];
        $exam['results'] = $grouped_results[$target_id] ?? [];
        $exam['total_submissions'] = count($exam['results']);
        
        // Calculate average score
        if ($exam['total_submissions'] > 0) {
            $sum = array_sum(array_column($exam['results'], 'percentage'));
            $exam['average_score'] = round($sum / $exam['total_submissions']);
        } else {
            $exam['average_score'] = 0;
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => $exams
    ]);

} catch (PDOException $e) {
    error_log("[Veeru] Get Teacher Exams Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
