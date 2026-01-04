<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : die();

// Get top 50 students based on total MCQ score
$query = "SELECT 
            u.id, 
            u.full_name, 
            u.profile_picture,
            SUM(ms.score) as total_score,
            COUNT(ms.id) as tests_taken
          FROM users u
          JOIN mcq_scores ms ON u.id = ms.user_id
          WHERE u.class_id = ? AND u.role = 'student'
          GROUP BY u.id
          ORDER BY total_score DESC
          LIMIT 50";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();

$leaderboard = [];
$rank = 1;

while ($row = $result->fetch_assoc()) {
    $row['rank'] = $rank++;
    $leaderboard[] = $row;
}

echo json_encode(['status' => 'success', 'data' => $leaderboard]);
?>
