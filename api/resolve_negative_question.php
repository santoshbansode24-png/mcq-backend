<?php
/**
 * Resolve Negative Question API
 * Allows students to re-solve a negatively marked question.
 * When answered correctly, updates is_correct = 1 so it is removed from Negative Questions tab.
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
$mcq_id = intval($input['mcq_id'] ?? 0);
$selected_option = strtolower(trim($input['selected_option'] ?? ''));
$answer_id = intval($input['answer_id'] ?? 0);

if ($user_id <= 0 || $mcq_id <= 0 || empty($selected_option)) {
    sendResponse('error', 'Missing required fields: user_id, mcq_id, selected_option', null, 400);
}

try {
    // 1. Fetch Question details
    $stmt = $pdo->prepare("SELECT mcq_id, correct_answer, explanation FROM mcqs WHERE mcq_id = ?");
    $stmt->execute([$mcq_id]);
    $mcq = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mcq) {
        sendResponse('error', 'MCQ not found', null, 404);
    }

    $raw_correct = strtolower(trim($mcq['correct_answer']));
    $correct_letter = str_replace('option_', '', $raw_correct);
    $selected_letter = str_replace('option_', '', $selected_option);

    if ($selected_letter === $correct_letter) {
        // Correctly Solved!
        // Mark as resolved/correct in exam_attempt_answers so it disappears from Negative Questions
        if ($answer_id > 0) {
            $stmtUpd = $pdo->prepare("UPDATE exam_attempt_answers SET is_correct = 1, marks_awarded = 4.00 WHERE answer_id = ? AND user_id = ?");
            $stmtUpd->execute([$answer_id, $user_id]);
        } else {
            $stmtUpd = $pdo->prepare("UPDATE exam_attempt_answers SET is_correct = 1, marks_awarded = 4.00 WHERE user_id = ? AND mcq_id = ? AND is_correct = 0");
            $stmtUpd->execute([$user_id, $mcq_id]);
        }

        // Also update negative_basket table
        try {
            $pdo->prepare("UPDATE negative_basket SET resolved = 1 WHERE student_id = ? AND question_id = ?")->execute([$user_id, $mcq_id]);
        } catch (Exception $e) {}

        // Also update mcq_attempts table so progress and accuracy reflect resolution
        try {
            $pdo->prepare("UPDATE mcq_attempts SET is_correct = 1 WHERE user_id = ? AND mcq_id = ?")->execute([$user_id, $mcq_id]);
        } catch (Exception $e) {}

        sendResponse('success', 'Question solved correctly! Removed from negative questions.', [
            'is_correct' => true,
            'mcq_id' => $mcq_id,
            'answer_id' => $answer_id,
            'correct_option' => $correct_letter,
            'explanation' => $mcq['explanation']
        ]);
    } else {
        // Still incorrect
        try {
            $pdo->prepare("UPDATE negative_basket SET wrong_attempt_count = wrong_attempt_count + 1, last_wrong_date = NOW() WHERE student_id = ? AND question_id = ?")->execute([$user_id, $mcq_id]);
        } catch (Exception $e) {}

        sendResponse('success', 'Incorrect answer. Review the explanation and try again.', [
            'is_correct' => false,
            'mcq_id' => $mcq_id,
            'answer_id' => $answer_id,
            'correct_option' => $correct_letter,
            'explanation' => $mcq['explanation']
        ]);
    }

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
