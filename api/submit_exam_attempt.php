<?php
/**
 * Submit Exam Attempt API - Calculates Net Score, Negative Marking, and Detailed Item Breakdown
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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$user_id = intval($input['user_id'] ?? 0);
$exam_id = intval($input['exam_id'] ?? 0);
$answers = $input['answers'] ?? [];
$time_spent_seconds = intval($input['time_spent_seconds'] ?? 0);

if ($user_id <= 0) {
    sendResponse('error', 'Invalid user_id', null, 400);
}

try {
    $pos_marks = isset($input['positive_marks']) ? floatval($input['positive_marks']) : 4.00;
    $neg_marks = isset($input['negative_marks']) ? floatval($input['negative_marks']) : 1.00;

    if ($exam_id > 0) {
        $stmtExam = $pdo->prepare("SELECT * FROM exams WHERE exam_id = ?");
        $stmtExam->execute([$exam_id]);
        $exam = $stmtExam->fetch(PDO::FETCH_ASSOC);
        if ($exam) {
            $pos_marks = floatval($exam['positive_marks'] ?? $pos_marks);
            $neg_marks = floatval($exam['negative_marks'] ?? $neg_marks);
        }
    }

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
        $mcq_id = intval($ans['mcq_id'] ?? 0);
        $selected = strtolower(trim($ans['selected_option'] ?? ''));
        $mcq_data = $mcqs_by_id[$mcq_id] ?? null;

        if (!$mcq_data) {
            if (!empty($ans['correct_answer'])) {
                $mcq_data = [
                    'mcq_id' => $mcq_id,
                    'chapter_id' => intval($ans['chapter_id'] ?? 0),
                    'question' => $ans['question'] ?? 'Question',
                    'option_a' => $ans['option_a'] ?? '',
                    'option_b' => $ans['option_b'] ?? '',
                    'option_c' => $ans['option_c'] ?? '',
                    'option_d' => $ans['option_d'] ?? '',
                    'correct_answer' => $ans['correct_answer'],
                    'explanation' => $ans['explanation'] ?? ''
                ];
            } else {
                continue;
            }
        }

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

    // Also keep exam_history synchronized
    try {
        $subNames = !empty($data['subject_names']) ? substr(trim($data['subject_names']), 0, 255) : 'My Exam';
        $chIds = !empty($data['chapter_ids']) ? substr(trim($data['chapter_ids']), 0, 500) : '';
        $ehPct = $total_q > 0 ? round(($correct_count / $total_q) * 100, 1) : 0;
        $pdo->prepare("
            INSERT INTO exam_history 
                (user_id, chapter_ids, subject_names, correct, incorrect, unanswered, total, percentage, time_seconds)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $user_id, $chIds, $subNames, $correct_count, $wrong_count, $unattempted_count, $total_q, $ehPct, $time_spent_seconds
        ]);
    } catch (Exception $e) {}

    $stmtAns = $pdo->prepare("
        INSERT INTO exam_attempt_answers 
        (attempt_id, user_id, exam_id, mcq_id, chapter_id, selected_option, correct_option, is_correct, marks_awarded)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtBasketUpsert = $pdo->prepare("
        INSERT INTO negative_basket 
        (student_id, question_id, subject_name, chapter_name, chapter_id, selected_answer, correct_answer, wrong_attempt_count, last_wrong_date, resolved)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 0)
        ON DUPLICATE KEY UPDATE 
            wrong_attempt_count = wrong_attempt_count + 1,
            selected_answer = VALUES(selected_answer),
            correct_answer = VALUES(correct_answer),
            last_wrong_date = NOW(),
            resolved = 0
    ");

    $defaultSubName = !empty($data['subject_names']) ? substr(trim($data['subject_names']), 0, 150) : 'Exam';

    foreach ($answer_details as $detail) {
        $stmtAns->execute([
            $attempt_id, $user_id, $exam_id, $detail['mcq_id'], $detail['chapter_id'],
            $detail['selected_option'], $detail['correct_option'], $detail['is_correct'], $detail['marks_awarded']
        ]);

        if ($detail['is_correct'] === 0) {
            try {
                $subName = $defaultSubName;
                $chName = 'Exam';
                if ($detail['chapter_id'] > 0) {
                    $chStmt = $pdo->prepare("SELECT ch.chapter_name, s.subject_name FROM chapters ch LEFT JOIN subjects s ON ch.subject_id = s.subject_id WHERE ch.chapter_id = ?");
                    $chStmt->execute([$detail['chapter_id']]);
                    $chInfo = $chStmt->fetch(PDO::FETCH_ASSOC);
                    if ($chInfo) {
                        $subName = $chInfo['subject_name'] ?? $defaultSubName;
                        $chName = $chInfo['chapter_name'] ?? 'Exam';
                    }
                }

                if ($detail['mcq_id'] > 0) {
                    $stmtBasketUpsert->execute([
                        $user_id, $detail['mcq_id'], $subName, $chName, $detail['chapter_id'],
                        $detail['selected_option'], $detail['correct_option']
                    ]);
                }
            } catch (Exception $e) {
                // Non-blocking
            }
        } elseif ($detail['is_correct'] === 1) {
            try {
                $pdo->prepare("UPDATE negative_basket SET resolved = 1 WHERE student_id = ? AND question_id = ?")->execute([$user_id, $detail['mcq_id']]);
            } catch (Exception $e) {}
        }
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
