<?php
/**
 * Save Mental Math Progress API
 * Endpoint: POST /api/student/save_mental_math.php
 */
require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();

$required = ['user_id', 'difficulty_level', 'questions_attempted', 'correct_answers', 'time_taken_seconds'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$user_id = (int)$input['user_id'];
$difficulty_level = strtolower(sanitizeInput($input['difficulty_level']));
$questions_attempted = (int)$input['questions_attempted'];
$correct_answers = (int)$input['correct_answers'];
$time_taken_seconds = (int)$input['time_taken_seconds'];

$wrong_answers = $questions_attempted - $correct_answers;
if ($wrong_answers < 0) $wrong_answers = 0;

$valid_levels = ['easy', 'medium', 'hard'];
if (!in_array($difficulty_level, $valid_levels)) {
    sendResponse('error', 'Invalid difficulty level. Must be easy, medium, or hard.', null, 400);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO mental_math_progress 
        (user_id, session_date, difficulty_level, questions_attempted, correct_answers, wrong_answers, time_taken_seconds, created_at)
        VALUES (?, CURDATE(), ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $difficulty_level,
        $questions_attempted,
        $correct_answers,
        $wrong_answers,
        $time_taken_seconds
    ]);

    sendResponse('success', 'Mental Math session progress saved securely!', ['session_id' => $pdo->lastInsertId()], 201);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        sendResponse('error', 'Database constraint violation. Invalid user_id.', null, 400);
    } else {
        sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
    }
}
?>
