<?php
/**
 * Get Scholarship Live Exam Leaderboard API
 * Returns rank list & student's personal rank
 * Veeru API
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=UTF-8');

// Inject CORS Headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($exam_id <= 0) {
    sendResponse('error', 'Invalid exam_id', null, 400);
}

try {
    // 1. Fetch Exam Details
    $stmtExam = $pdo->prepare("SELECT exam_id, title, total_questions, positive_marks FROM exams WHERE exam_id = ?");
    $stmtExam->execute([$exam_id]);
    $exam = $stmtExam->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        sendResponse('error', 'Exam not found', null, 404);
    }

    // 2. Fetch Leaderboard (Rank by net_score DESC, time_spent_seconds ASC, completed_at ASC)
    $sql = "
        SELECT 
            ea.attempt_id,
            ea.user_id,
            u.name as student_name,
            u.email,
            ea.correct_count,
            ea.wrong_count,
            ea.unattempted_count,
            ea.net_score,
            ea.accuracy_percentage,
            ea.time_spent_seconds,
            ea.completed_at
        FROM exam_attempts ea
        JOIN users u ON ea.user_id = u.user_id
        WHERE ea.exam_id = ?
        ORDER BY ea.net_score DESC, ea.time_spent_seconds ASC, ea.completed_at ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exam_id]);
    $rawRanks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Deduplicate so each student only appears once with their best attempt
    $allRanks = [];
    $seenUsers = [];
    foreach ($rawRanks as $row) {
        $uId = $row['user_id'];
        if (!isset($seenUsers[$uId])) {
            $seenUsers[$uId] = true;
            $allRanks[] = $row;
        }
    }

    $totalParticipants = count($allRanks);
    $userRankData = null;
    $leaderboard = [];

    foreach ($allRanks as $index => $row) {
        $rank = $index + 1;
        $row['rank'] = $rank;
        $row['formatted_time'] = gmdate("i:s", intval($row['time_spent_seconds']));

        // Check if current user
        if ($user_id > 0 && intval($row['user_id']) === $user_id) {
            $userRankData = $row;
            $percentile = $totalParticipants > 1 
                ? round((($totalParticipants - $rank) / ($totalParticipants - 1)) * 100, 1)
                : 100.0;
            $userRankData['percentile'] = $percentile;
        }

        // Top 20 for display
        if ($rank <= 20) {
            $leaderboard[] = $row;
        }
    }

    sendResponse('success', 'Leaderboard loaded', [
        'exam' => $exam,
        'total_participants' => $totalParticipants,
        'top_rankers' => $leaderboard,
        'user_rank' => $userRankData
    ]);

} catch (Exception $e) {
    sendResponse('error', 'Error: ' . $e->getMessage(), null, 500);
}
