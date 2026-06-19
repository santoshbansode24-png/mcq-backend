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
    
    $class_id = $exam['class_id'];
    
    // Self-healing: Ensure class_exam_results table exists
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
            INDEX idx_user_id (user_id),
            UNIQUE KEY unique_attempt (update_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Query actual submissions from class_exam_results
    $query = "SELECT 
                u.user_id as id, 
                u.name as full_name, 
                r.correct as total_score,
                r.incorrect,
                r.unanswered,
                r.total,
                r.time_seconds,
                CASE WHEN r.total > 0 THEN ROUND((r.correct / r.total * 100), 2) ELSE 0.0 END as percentage
              FROM users u
              JOIN class_exam_results r ON u.user_id = r.user_id
              WHERE r.update_id = ?
                AND u.class_id = ? 
                AND u.user_type = 'student'
              ORDER BY total_score DESC, percentage DESC, r.time_seconds ASC
              LIMIT 50";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$live_exam_id, $class_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaderboard = [];
    $rank = 1;

    if ($result) {
        foreach ($result as $row) {
            $row['rank'] = $rank++;
            $row['total_score'] = (int)$row['total_score'];
            $row['incorrect'] = (int)($row['incorrect'] ?? 0);
            $row['unanswered'] = (int)($row['unanswered'] ?? 0);
            $row['total'] = (int)($row['total'] ?? 0);
            $row['time_seconds'] = (int)($row['time_seconds'] ?? 0);
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
