<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : die();

// 1. Total Tests Taken & Average Score
$statsQuery = "SELECT 
                COUNT(*) as total_tests, 
                AVG(score) as avg_score,
                SUM(score) as total_points
               FROM mcq_scores 
               WHERE user_id = ?";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// 2. Subject-wise Performance
$subjectQuery = "SELECT 
                    s.subject_name, 
                    AVG(ms.score) as avg_score,
                    COUNT(ms.id) as tests_taken
                 FROM mcq_scores ms
                 JOIN chapters c ON ms.chapter_id = c.chapter_id
                 JOIN subjects s ON c.subject_id = s.subject_id
                 WHERE ms.user_id = ?
                 GROUP BY s.subject_id";
$stmt = $conn->prepare($subjectQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Recent Activity (Last 5 tests)
$recentQuery = "SELECT 
                  c.chapter_name, 
                  ms.score, 
                  ms.total_questions,
                  ms.created_at
                FROM mcq_scores ms
                JOIN chapters c ON ms.chapter_id = c.chapter_id
                WHERE ms.user_id = ?
                ORDER BY ms.created_at DESC
                LIMIT 5";
$stmt = $conn->prepare($recentQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => [
        'overview' => $stats,
        'subjects' => $subjects,
        'recent' => $recent
    ]
]);
?>
