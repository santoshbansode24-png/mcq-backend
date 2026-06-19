<?php
/**
 * Get MCQ Leaderboard API
 * Veeru
 * 
 * Endpoint: GET /api/get_mcq_leaderboard.php?class_id=1
 */

require_once 'cors_middleware.php';

try {
    require_once '../config/db.php';
    
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
    
    if ($class_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid class_id']);
        exit;
    }
    
    // Check if class_id is a classroom ID
    $stmt_cr = $pdo->prepare("SELECT COUNT(*) FROM classrooms WHERE class_id = ?");
    $stmt_cr->execute([$class_id]);
    $is_classroom = $stmt_cr->fetchColumn() > 0;

    if ($is_classroom) {
        // Query leaderboard for students in this specific classroom
        $query = "SELECT 
                    u.user_id as id, 
                    u.name as full_name, 
                    u.profile_picture,
                    SUM(ms.mcq_score) as total_score,
                    COUNT(ms.progress_id) as tests_taken
                  FROM users u
                  JOIN student_class_mapping scm ON u.user_id = scm.student_id
                  JOIN student_progress ms ON u.user_id = ms.user_id
                  WHERE scm.class_id = ? AND u.user_type = 'student'
                  GROUP BY u.user_id
                  ORDER BY total_score DESC
                  LIMIT 50";
    } else {
        // Query leaderboard for all students in this generic class
        $query = "SELECT 
                    u.user_id as id, 
                    u.name as full_name, 
                    u.profile_picture,
                    SUM(ms.mcq_score) as total_score,
                    COUNT(ms.progress_id) as tests_taken
                  FROM users u
                  JOIN student_progress ms ON u.user_id = ms.user_id
                  WHERE u.class_id = ? AND u.user_type = 'student'
                  GROUP BY u.user_id
                  ORDER BY total_score DESC
                  LIMIT 50";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$class_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaderboard = [];
    $rank = 1;

    if ($result) {
        foreach ($result as $row) {
            $row['rank'] = $rank++;
            $row['total_score'] = (int)$row['total_score'];
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
