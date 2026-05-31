<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../cors_middleware.php';

try {
    $live_exam_id = isset($_GET['live_exam_id']) ? (int)$_GET['live_exam_id'] : 0;
    
    if ($live_exam_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid live_exam_id']);
        exit;
    }
    
    // First get the live exam details
    $examStmt = $pdo->prepare("SELECT * FROM live_exams WHERE id = ?");
    $examStmt->execute([$live_exam_id]);
    $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        echo json_encode(['status' => 'error', 'message' => 'Live exam not found']);
        exit;
    }
    
    $chapter_id = $exam['chapter_id'];
    $class_id = $exam['class_id'];
    $created_at = $exam['created_at'];
    
    // Calculate expiry time to bound the query
    $durationSeconds = $exam['duration_minutes'] * 60;
    $expiryTime = date('Y-m-d H:i:s', strtotime($created_at) + $durationSeconds + 300); // add 5 mins buffer
    
    $query = "SELECT 
                u.user_id as id, 
                u.name as full_name, 
                MAX(ms.mcq_score) as total_score,
                COUNT(ms.progress_id) as tests_taken,
                MAX(ms.percentage) as percentage
              FROM users u
              JOIN student_progress ms ON u.user_id = ms.user_id
              WHERE u.class_id = ? 
                AND u.user_type = 'student' 
                AND ms.chapter_id = ?
                AND ms.completed_at >= ?
                AND ms.completed_at <= ?
              GROUP BY u.user_id
              ORDER BY total_score DESC, percentage DESC
              LIMIT 50";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$class_id, $chapter_id, $created_at, $expiryTime]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaderboard = [];
    $rank = 1;

    if ($result) {
        foreach ($result as $row) {
            $row['rank'] = $rank++;
            $row['total_score'] = (int)$row['total_score'];
            $row['percentage'] = (float)$row['percentage'];
            $leaderboard[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'data' => $leaderboard]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Fatal Error: ' . $e->getMessage()
    ]);
}
?>
