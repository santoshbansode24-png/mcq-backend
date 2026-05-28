<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($class_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid class_id']);
    exit;
}

try {
    // Get top 50 students based on total MCQ score for a specific class
    $query = "SELECT 
                u.user_id as id, 
                u.name as full_name, 
                SUM(ms.score) as total_score,
                COUNT(ms.id) as tests_taken
              FROM users u
              JOIN mcq_scores ms ON u.user_id = ms.user_id
              WHERE u.class_id = ? AND u.user_type = 'student'
              GROUP BY u.user_id
              ORDER BY total_score DESC
              LIMIT 50";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$class_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaderboard = [];
    $rank = 1;

    foreach ($result as $row) {
        $row['rank'] = $rank++;
        // ensure integer casting for total_score if needed
        $row['total_score'] = (int)$row['total_score'];
        $leaderboard[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $leaderboard]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
