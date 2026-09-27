<?php
/**
 * Student Performance Report & Negative Questions API
 * Veeru API
 */
require_once '../config/db.php';
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

$user_id = intval($_GET['user_id'] ?? ($_POST['user_id'] ?? 0));
$view = $_GET['view'] ?? ($_POST['view'] ?? 'full'); // full, negative_questions, weak_chapters, attempts

if ($user_id <= 0) {
    sendResponse('error', 'Invalid user_id parameter', null, 400);
}

try {
    // 1. Overall Stats Summary
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_exams,
            IFNULL(AVG(net_score), 0.00) as avg_net_score,
            IFNULL(AVG(accuracy_percentage), 0.00) as overall_accuracy_pct,
            IFNULL(SUM(negative_deduction), 0.00) as total_negative_marks_lost,
            IFNULL(SUM(positive_score), 0.00) as total_positive_marks,
            IFNULL(SUM(correct_count), 0) as total_correct,
            IFNULL(SUM(wrong_count), 0) as total_wrong,
            IFNULL(SUM(unattempted_count), 0) as total_unattempted
        FROM exam_attempts
        WHERE user_id = ?
    ");
    $stmtStats->execute([$user_id]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Format numbers
    $stats['avg_net_score'] = round(floatval($stats['avg_net_score']), 2);
    $stats['overall_accuracy_pct'] = round(floatval($stats['overall_accuracy_pct']), 2);
    $stats['total_negative_marks_lost'] = round(floatval($stats['total_negative_marks_lost']), 2);
    $stats['total_positive_marks'] = round(floatval($stats['total_positive_marks']), 2);

    // 2. Dedicated Negative Questions List (Wrong Answers with Negative Penalty)
    $stmtNeg = $pdo->prepare("
        SELECT 
            ea.answer_id, ea.attempt_id, ea.exam_id, ea.mcq_id, ea.selected_option, ea.correct_option,
            ea.marks_awarded, ea.created_at as attempted_at,
            m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.explanation,
            ch.chapter_name, s.subject_name, e.title as exam_title
        FROM exam_attempt_answers ea
        JOIN mcqs m ON ea.mcq_id = m.mcq_id
        LEFT JOIN chapters ch ON ea.chapter_id = ch.chapter_id
        LEFT JOIN subjects s ON ch.subject_id = s.subject_id
        LEFT JOIN exams e ON ea.exam_id = e.exam_id
        WHERE ea.user_id = ? AND ea.is_correct = 0
        ORDER BY ea.answer_id DESC LIMIT 100
    ");
    $stmtNeg->execute([$user_id]);
    $negative_questions = $stmtNeg->fetchAll(PDO::FETCH_ASSOC);

    // 3. Chapter Weakness Breakdown (Strong vs Average vs Weak)
    $stmtChapters = $pdo->prepare("
        SELECT 
            ch.chapter_id, ch.chapter_name, s.subject_name,
            COUNT(*) as total_attempted,
            SUM(CASE WHEN ea.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
            SUM(CASE WHEN ea.is_correct = 0 THEN 1 ELSE 0 END) as wrong_count,
            SUM(ea.marks_awarded) as net_chapter_score
        FROM exam_attempt_answers ea
        JOIN chapters ch ON ea.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE ea.user_id = ?
        GROUP BY ch.chapter_id
        ORDER BY s.subject_name ASC, ch.chapter_name ASC
    ");
    $stmtChapters->execute([$user_id]);
    $chapter_rows = $stmtChapters->fetchAll(PDO::FETCH_ASSOC);

    $chapter_breakdown = [
        'strong_chapters' => [],  // >= 80% accuracy
        'average_chapters' => [], // 60% - 79% accuracy
        'weak_chapters' => []     // < 60% accuracy (Needs Revision!)
    ];

    foreach ($chapter_rows as $ch) {
        $attempted = intval($ch['total_attempted']);
        $correct = intval($ch['correct_count']);
        $accuracy = $attempted > 0 ? round(($correct / $attempted) * 100, 1) : 0.0;

        $ch_item = [
            'chapter_id' => $ch['chapter_id'],
            'chapter_name' => $ch['chapter_name'],
            'subject_name' => $ch['subject_name'],
            'total_attempted' => $attempted,
            'correct_count' => $correct,
            'wrong_count' => intval($ch['wrong_count']),
            'accuracy_pct' => $accuracy
        ];

        if ($accuracy >= 80.0) {
            $ch_item['status'] = 'Strong';
            $chapter_breakdown['strong_chapters'][] = $ch_item;
        } elseif ($accuracy >= 60.0) {
            $ch_item['status'] = 'Average';
            $chapter_breakdown['average_chapters'][] = $ch_item;
        } else {
            $ch_item['status'] = 'Weak';
            $chapter_breakdown['weak_chapters'][] = $ch_item;
        }
    }

    // 4. Class Rank Calculation (Peer Benchmark)
    $stmtClass = $pdo->prepare("SELECT class_id, board_type FROM users WHERE user_id = ?");
    $stmtClass->execute([$user_id]);
    $u_info = $stmtClass->fetch(PDO::FETCH_ASSOC);

    $rank_info = [
        'user_rank' => 1,
        'total_students' => 1,
        'percentile' => 100.0
    ];

    if ($u_info && !empty($u_info['class_id'])) {
        $stmtRank = $pdo->prepare("
            SELECT u.user_id, AVG(ea.net_score) as avg_score
            FROM users u
            JOIN exam_attempts ea ON u.user_id = ea.user_id
            WHERE u.class_id = ? AND u.user_type = 'student'
            GROUP BY u.user_id
            ORDER BY avg_score DESC
        ");
        $stmtRank->execute([$u_info['class_id']]);
        $rank_rows = $stmtRank->fetchAll(PDO::FETCH_ASSOC);

        $total_students = count($rank_rows);
        $user_rank = 1;

        foreach ($rank_rows as $idx => $r) {
            if ($r['user_id'] == $user_id) {
                $user_rank = $idx + 1;
                break;
            }
        }

        $percentile = $total_students > 1 ? round(((($total_students - $user_rank) / $total_students) * 100), 1) : 100.0;

        $rank_info = [
            'user_rank' => $user_rank,
            'total_students' => max(1, $total_students),
            'percentile' => $percentile
        ];
    }

    // 5. Recent Attempts List
    $stmtAttempts = $pdo->prepare("
        SELECT 
            ea.*, e.title as exam_title, s.subject_name
        FROM exam_attempts ea
        LEFT JOIN exams e ON ea.exam_id = e.exam_id
        LEFT JOIN subjects s ON e.subject_id = s.subject_id
        WHERE ea.user_id = ?
        ORDER BY ea.completed_at DESC LIMIT 20
    ");
    $stmtAttempts->execute([$user_id]);
    $recent_attempts = $stmtAttempts->fetchAll(PDO::FETCH_ASSOC);

    $response_payload = [
        'stats' => $stats,
        'negative_questions' => $negative_questions,
        'chapter_breakdown' => $chapter_breakdown,
        'rank_info' => $rank_info,
        'recent_attempts' => $recent_attempts
    ];

    sendResponse('success', 'Student performance report retrieved successfully', $response_payload);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
