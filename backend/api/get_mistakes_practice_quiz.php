<?php
/**
 * 1-Click "Practice My Mistakes" Quiz API
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

$user_id = intval($_GET['user_id'] ?? ($_POST['user_id'] ?? 0));
$limit = intval($_GET['limit'] ?? 10);

if ($user_id <= 0) {
    sendResponse('error', 'Invalid user_id parameter', null, 400);
}

try {
    // 1. First attempt to pull from negative_basket
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            m.mcq_id, m.chapter_id, m.question, m.option_a, m.option_b, m.option_c, m.option_d,
            m.correct_answer, m.explanation, m.difficulty,
            COALESCE(ch.chapter_name, nb.chapter_name, 'Practice') as chapter_name,
            COALESCE(s.subject_name, nb.subject_name, 'General') as subject_name
        FROM negative_basket nb
        JOIN mcqs m ON nb.question_id = m.mcq_id
        LEFT JOIN chapters ch ON m.chapter_id = ch.chapter_id
        LEFT JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE nb.student_id = ? AND nb.resolved = 0
        ORDER BY nb.wrong_attempt_count DESC, RAND()
        LIMIT ?
    ");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fallback to exam_attempt_answers and mcq_attempts if negative_basket is empty
    if (empty($questions)) {
        $stmtFallback = $pdo->prepare("
            SELECT DISTINCT 
                m.mcq_id, m.chapter_id, m.question, m.option_a, m.option_b, m.option_c, m.option_d,
                m.correct_answer, m.explanation, m.difficulty,
                COALESCE(ch.chapter_name, 'Practice') as chapter_name,
                COALESCE(s.subject_name, 'General') as subject_name
            FROM mcq_attempts ma
            JOIN mcqs m ON ma.mcq_id = m.mcq_id
            LEFT JOIN chapters ch ON m.chapter_id = ch.chapter_id
            LEFT JOIN subjects s ON ch.subject_id = s.subject_id
            WHERE ma.user_id = ? AND ma.is_correct = 0
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmtFallback->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmtFallback->bindValue(2, $limit, PDO::PARAM_INT);
        $stmtFallback->execute();
        $questions = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
    }

    $formatted_quiz = [];
    foreach ($questions as $q) {
        $correct_letter = str_replace('option_', '', strtolower(trim($q['correct_answer'])));
        $formatted_quiz[] = [
            'mcq_id' => intval($q['mcq_id']),
            'question' => $q['question'],
            'options' => [
                'a' => $q['option_a'],
                'b' => $q['option_b'],
                'c' => $q['option_c'],
                'd' => $q['option_d']
            ],
            'correct_answer' => $correct_letter,
            'explanation' => $q['explanation'],
            'chapter_name' => $q['chapter_name'],
            'subject_name' => $q['subject_name']
        ];
    }

    sendResponse('success', 'Mistakes practice quiz retrieved successfully', [
        'total_questions' => count($formatted_quiz),
        'quiz_title' => '⚡ Practice My Mistakes Quiz',
        'questions' => $formatted_quiz
    ]);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
