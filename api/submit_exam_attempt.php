<?php
/**
 * Submit Exam Attempt API - Calculates Net Score, Negative Marking, and Detailed Item Breakdown
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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$user_id = intval($input['user_id'] ?? 0);
$exam_id = intval($input['exam_id'] ?? 0);
$answers = $input['answers'] ?? [];
$time_spent_seconds = intval($input['time_spent_seconds'] ?? 0);

if ($user_id <= 0 || $exam_id <= 0) {
    sendResponse('error', 'Invalid user_id or exam_id', null, 400);
}

try {
    $stmtExam = $pdo->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $stmtExam->execute([$exam_id]);
    $exam = $stmtExam->fetch(PDO::FETCH_ASSOC);

    $pos_marks = floatval($exam['positive_marks'] ?? 4.00);
    $neg_marks = floatval($exam['negative_marks'] ?? 1.00);

    $mcq_ids = array_column($answers, 'mcq_id');
    $mcqs_by_id = [];

    if (!empty($mcq_ids)) {
        $in_clause = implode(',', array_map('intval', array_unique($mcq_ids)));
        $stmtMCQs = $pdo->query("SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation FROM mcqs WHERE mcq_id IN ($in_clause)");
        while ($row = $stmtMCQs->fetch(PDO::FETCH_ASSOC)) {
            $mcqs_by_id[$row['mcq_id']] = $row;
        }
    }

    $total_q = count($answers);
    $correct_count = 0;
    $wrong_count = 0;
    $unattempted_count = 0;

    $answer_details = [];

    foreach ($answers as $ans) {
        $mcq_id = intval($ans['mcq_id']);
        $selected = strtolower(trim($ans['selected_option'] ?? ''));
        $mcq_data = $mcqs_by_id[$mcq_id] ?? null;

        if (!$mcq_data) continue;

        $correct_opt = strtolower(trim($mcq_data['correct_answer']));
        $correct_letter = str_replace('option_', '', $correct_opt);

        if (empty($selected) || $selected === 'skip' || $selected === 'none') {
            $unattempted_count++;
            $is_correct = -1;
            $marks_awarded = 0.00;
        } elseif ($selected === $correct_letter || $selected === 'option_' . $correct_letter) {
            $correct_count++;
            $is_correct = 1;
            $marks_awarded = $pos_marks;
        } else {
            $wrong_count++;
            $is_correct = 0;
            $marks_awarded = -$neg_marks;
        }

        $answer_details[] = [
            'mcq_id' => $mcq_id,
            'chapter_id' => $mcq_data['chapter_id'],
            'selected_option' => $selected,
            'correct_option' => $correct_letter,
            'is_correct' => $is_correct,
            'marks_awarded' => $marks_awarded,
            'question' => $mcq_data['question'],
            'option_a' => $mcq_data['option_a'],
            'option_b' => $mcq_data['option_b'],
            'option_c' => $mcq_data['option_c'],
            'option_d' => $mcq_data['option_d'],
            'explanation' => $mcq_data['explanation']
        ];
    }

    $pos_score = $correct_count * $pos_marks;
    $neg_deduction = $wrong_count * $neg_marks;
    $net_score = $pos_score - $neg_deduction;
    $max_possible_score = $total_q * $pos_marks;

    $attempted_q = $correct_count + $wrong_count;
    $accuracy_pct = $attempted_q > 0 ? round(($correct_count / $attempted_q) * 100, 2) : 0.00;

    $stmtAttempt = $pdo->prepare("
        INSERT INTO exam_attempts 
        (exam_id, user_id, total_questions, correct_count, wrong_count, unattempted_count, positive_score, negative_deduction, net_score, max_possible_score, accuracy_percentage, time_spent_seconds)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtAttempt->execute([
        $exam_id, $user_id, $total_q, $correct_count, $wrong_count, $unattempted_count,
        $pos_score, $neg_deduction, $net_score, $max_possible_score, $accuracy_pct, $time_spent_seconds
    ]);
    $attempt_id = $pdo->lastInsertId();

    $stmtAns = $pdo->prepare("
        INSERT INTO exam_attempt_answers 
        (attempt_id, user_id, exam_id, mcq_id, chapter_id, selected_option, correct_option, is_correct, marks_awarded)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($answer_details as $detail) {
        $stmtAns->execute([
            $attempt_id, $user_id, $exam_id, $detail['mcq_id'], $detail['chapter_id'],
            $detail['selected_option'], $detail['correct_option'], $detail['is_correct'], $detail['marks_awarded']
        ]);
    }

    $response_data = [
        'attempt_id' => $attempt_id,
        'exam_id' => $exam_id,
        'user_id' => $user_id,
        'total_questions' => $total_q,
        'correct_count' => $correct_count,
        'wrong_count' => $wrong_count,
        'unattempted_count' => $unattempted_count,
        'positive_score' => $pos_score,
        'negative_deduction' => $neg_deduction,
        'net_score' => $net_score,
        'max_possible_score' => $max_possible_score,
        'accuracy_percentage' => $accuracy_pct,
        'time_spent_seconds' => $time_spent_seconds,
        'answers' => $answer_details
    ];

    sendResponse('success', 'Exam attempt submitted successfully', $response_data);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
