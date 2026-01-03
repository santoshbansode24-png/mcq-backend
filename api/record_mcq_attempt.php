<?php
/**
 * Record MCQ Attempt API
 * Veeru
 * 
 * Endpoint: POST /api/record_mcq_attempt.php
 * Purpose: Record individual MCQ attempt for progress tracking
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

// Validate required fields
$required = ['user_id', 'mcq_id', 'chapter_id', 'selected_answer', 'correct_answer', 'is_correct'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

// Sanitize inputs
$user_id = intval($input['user_id']);
$mcq_id = intval($input['mcq_id']);
$chapter_id = intval($input['chapter_id']);
$selected_answer = sanitizeInput($input['selected_answer']);
$correct_answer = sanitizeInput($input['correct_answer']);
$is_correct = filter_var($input['is_correct'], FILTER_VALIDATE_BOOLEAN);

// Validate values
if ($user_id <= 0 || $mcq_id <= 0 || $chapter_id <= 0) {
    sendResponse('error', 'Invalid input values', null, 400);
}

try {
    // Insert attempt record
    $stmt = $pdo->prepare("
        INSERT INTO mcq_attempts 
        (user_id, mcq_id, chapter_id, selected_answer, correct_answer, is_correct, attempted_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id, 
        $mcq_id, 
        $chapter_id, 
        $selected_answer, 
        $correct_answer, 
        $is_correct ? 1 : 0
    ]);
    
    $attempt_id = $pdo->lastInsertId();
    
    // Get updated progress for this chapter
    $progressStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM mcqs WHERE chapter_id = ?) as total_mcqs,
            (SELECT COUNT(DISTINCT mcq_id) FROM mcq_attempts WHERE user_id = ? AND chapter_id = ?) as solved_mcqs
    ");
    $progressStmt->execute([$chapter_id, $user_id, $chapter_id]);
    $progress = $progressStmt->fetch();
    
    $total = intval($progress['total_mcqs']);
    $solved = intval($progress['solved_mcqs']);
    $percentage = $total > 0 ? round(($solved / $total) * 100, 1) : 0;
    
    // Success response
    sendResponse('success', 'MCQ attempt recorded successfully', [
        'attempt_id' => $attempt_id,
        'is_correct' => $is_correct,
        'chapter_progress' => [
            'total_mcqs' => $total,
            'solved_mcqs' => $solved,
            'completion_percentage' => $percentage
        ]
    ], 201);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
