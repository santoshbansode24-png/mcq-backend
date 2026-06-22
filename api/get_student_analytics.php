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

    // 1. Total Tests Taken & Average Score (Practice MCQs)
    $statsQuery = "SELECT 
                    COUNT(*) as total_tests, 
                    AVG(percentage) as avg_score,
                    SUM(mcq_score) as total_points
                   FROM student_progress 
                   WHERE user_id = ?";
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 1b. Live / Class Exams Overview
    $liveStatsQuery = "SELECT 
                        COUNT(*) as total_live_exams,
                        AVG(CASE WHEN total > 0 THEN (correct / total * 100) ELSE 0 END) as avg_live_score,
                        SUM(correct) as total_live_points
                       FROM class_exam_results 
                       WHERE user_id = ?";
    $stmt = $pdo->prepare($liveStatsQuery);
    $stmt->execute([$user_id]);
    $liveStats = $stmt->fetch(PDO::FETCH_ASSOC);

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

    // 3b. Class Exam History
    $liveHistoryQuery = "SELECT 
                           COALESCE(le.title, cu.title, 'Class Exam') as exam_title,
                           r.correct,
                           r.total,
                           r.time_seconds,
                           r.submitted_at as created_at
                          FROM class_exam_results r
                          LEFT JOIN live_exams le ON r.update_id = le.id
                          LEFT JOIN class_updates cu ON r.update_id = cu.update_id
                          WHERE r.user_id = ?
                          ORDER BY r.submitted_at DESC
                          LIMIT 50";
    $stmt = $pdo->prepare($liveHistoryQuery);
    $stmt->execute([$user_id]);
    $liveHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3c. Monthly progress trend for Academic Year
    // Combines practice tests and class exams by month
    $trendQuery = "SELECT 
                    DATE_FORMAT(date_val, '%M %Y') as month_name,
                    DATE_FORMAT(date_val, '%Y-%m') as month_key,
                    ROUND(AVG(score_pct), 1) as avg_score,
                    type
                   FROM (
                       SELECT completed_at as date_val, percentage as score_pct, 'practice' as type 
                       FROM student_progress 
                       WHERE user_id = ? AND completed_at IS NOT NULL
                       UNION ALL
                       SELECT submitted_at as date_val, (correct/total * 100) as score_pct, 'exam' as type 
                       FROM class_exam_results 
                       WHERE user_id = ? AND total > 0 AND submitted_at IS NOT NULL
                   ) as combined
                   GROUP BY month_key, type
                   ORDER BY month_key ASC";
    $stmt = $pdo->prepare($trendQuery);
    $stmt->execute([$user_id, $user_id]);
    $trendRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group trend by month for easier rendering in React Native
    $monthlyTrend = [];
    foreach ($trendRaw as $row) {
        $mKey = $row['month_key'];
        if (!isset($monthlyTrend[$mKey])) {
            $monthlyTrend[$mKey] = [
                'month_name' => $row['month_name'],
                'month_key' => $mKey,
                'practice' => null,
                'exam' => null
            ];
        }
        $monthlyTrend[$mKey][$row['type']] = (float)$row['avg_score'];
    }
    // Convert associative array back to sequential list sorted DESC (latest months first)
    krsort($monthlyTrend);
    $monthlyTrend = array_values($monthlyTrend);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'overview' => $stats,
            'live_overview' => $liveStats,
            'subjects' => $subjects,
            'recent' => $recent,
            'live_history' => $liveHistory,
            'monthly_trend' => $monthlyTrend
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
