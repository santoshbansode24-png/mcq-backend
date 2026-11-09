<?php
/**
 * Get Scholarship Live Exam Questions API
 * Returns questions for a specific Live Scholarship Exam
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

if ($exam_id <= 0) {
    sendResponse('error', 'Invalid exam_id', null, 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE exam_id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        sendResponse('error', 'Exam not found', null, 404);
    }

    $questions = [];
    $question_ids_str = trim($exam['question_ids'] ?? '');

    if (!empty($question_ids_str)) {
        $clean_ids = array_filter(array_map('intval', explode(',', $question_ids_str)));
        if (!empty($clean_ids)) {
            $inPlaceholders = implode(',', array_fill(0, count($clean_ids), '?'));
            $sql = "
                SELECT m.mcq_id, m.chapter_id, m.question, m.option_a, m.option_b, m.option_c, m.option_d,
                       m.correct_answer, m.explanation, ch.chapter_name, s.subject_name
                FROM mcqs m
                LEFT JOIN chapters ch ON m.chapter_id = ch.chapter_id
                LEFT JOIN subjects s ON ch.subject_id = s.subject_id
                WHERE m.mcq_id IN ($inPlaceholders)
            ";
            $stmtQ = $pdo->prepare($sql);
            $stmtQ->execute($clean_ids);
            $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Fallback: if questions empty, fetch from selected chapters
    if (empty($questions) && !empty($exam['selected_chapters'])) {
        $ch_ids = array_filter(array_map('intval', explode(',', $exam['selected_chapters'])));
        if (!empty($ch_ids)) {
            $inPlaceholders = implode(',', array_fill(0, count($ch_ids), '?'));
            $limit = intval($exam['total_questions'] > 0 ? $exam['total_questions'] : 50);
            $sql = "
                SELECT m.mcq_id, m.chapter_id, m.question, m.option_a, m.option_b, m.option_c, m.option_d,
                       m.correct_answer, m.explanation, ch.chapter_name, s.subject_name
                FROM mcqs m
                LEFT JOIN chapters ch ON m.chapter_id = ch.chapter_id
                LEFT JOIN subjects s ON ch.subject_id = s.subject_id
                WHERE m.chapter_id IN ($inPlaceholders)
                ORDER BY RAND()
                LIMIT $limit
            ";
            $stmtQ = $pdo->prepare($sql);
            $stmtQ->execute($ch_ids);
            $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    sendResponse('success', 'Questions fetched successfully', [
        'exam' => [
            'exam_id' => $exam['exam_id'],
            'title' => $exam['title'],
            'duration_minutes' => intval($exam['duration_minutes']),
            'positive_marks' => floatval($exam['positive_marks']),
            'negative_marks' => floatval($exam['negative_marks']),
            'total_questions' => count($questions),
            'instructions' => $exam['instructions']
        ],
        'questions' => $questions
    ]);

} catch (Exception $e) {
    sendResponse('error', 'Error: ' . $e->getMessage(), null, 500);
}
