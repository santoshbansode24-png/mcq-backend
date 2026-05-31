<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

try {
    // 1. Total Tests Taken & Average Score
    $statsQuery = "SELECT 
                    COUNT(*) as total_tests, 
                    AVG(percentage) as avg_score,
                    SUM(mcq_score) as total_points
                   FROM student_progress 
                   WHERE user_id = ?";
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Subject-wise Performance
    $subjectQuery = "SELECT 
                        s.subject_name, 
                        AVG(ms.percentage) as avg_score,
                        COUNT(ms.progress_id) as tests_taken
                     FROM student_progress ms
                     JOIN chapters c ON ms.chapter_id = c.chapter_id
                     JOIN subjects s ON c.subject_id = s.subject_id
                     WHERE ms.user_id = ?
                     GROUP BY s.subject_id";
    $stmt = $pdo->prepare($subjectQuery);
    $stmt->execute([$user_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Recent Activity (Last 50 tests)
    $recentQuery = "SELECT 
                      c.chapter_name, 
                      ms.mcq_score as score, 
                      ms.total_mcq as total_questions,
                      ms.completed_at as created_at
                    FROM student_progress ms
                    JOIN chapters c ON ms.chapter_id = c.chapter_id
                    WHERE ms.user_id = ?
                    ORDER BY ms.completed_at DESC
                    LIMIT 50";
    $stmt = $pdo->prepare($recentQuery);
    $stmt->execute([$user_id]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'overview' => $stats,
            'subjects' => $subjects,
            'recent' => $recent
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
