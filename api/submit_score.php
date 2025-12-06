<?php
/**
 * Submit Score API
 * MCQ Project 2.0
 * 
 * Endpoint: POST /api/submit_score.php
 * Purpose: Submit quiz score and save to student progress
 */

require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();

// Validate required fields
$required = ['user_id', 'chapter_id', 'mcq_score', 'total_mcq'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

// Sanitize and validate inputs
$user_id = intval($input['user_id']);
$chapter_id = intval($input['chapter_id']);
$mcq_score = intval($input['mcq_score']);
$total_mcq = intval($input['total_mcq']);

// Validate values
if ($user_id <= 0 || $chapter_id <= 0 || $total_mcq <= 0) {
    sendResponse('error', 'Invalid input values', null, 400);
}

if ($mcq_score < 0 || $mcq_score > $total_mcq) {
    sendResponse('error', 'Score cannot be negative or greater than total questions', null, 400);
}

// Calculate percentage
$percentage = ($total_mcq > 0) ? round(($mcq_score / $total_mcq) * 100, 2) : 0;

try {
    // Insert progress record
    $stmt = $pdo->prepare("
        INSERT INTO student_progress 
        (user_id, chapter_id, mcq_score, total_mcq, percentage, completed_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$user_id, $chapter_id, $mcq_score, $total_mcq, $percentage]);
    
    // Get the inserted progress_id
    $progress_id = $pdo->lastInsertId();
    
    // Prepare response data
    $responseData = [
        'progress_id' => $progress_id,
        'user_id' => $user_id,
        'chapter_id' => $chapter_id,
        'mcq_score' => $mcq_score,
        'total_mcq' => $total_mcq,
        'percentage' => $percentage,
        'grade' => getGrade($percentage)
    ];
    
    // Check for Badges
    // require_once 'badge_helper.php';
    // $new_badges = checkAndAwardBadges($pdo, $user_id, 'quiz_submission');
    // if (!empty($new_badges)) {
    //     $responseData['new_badges'] = $new_badges;
    // }

    // Success response
    sendResponse('success', 'Score submitted successfully', $responseData, 201);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}

/**
 * Helper function to get grade based on percentage
 */
function getGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    if ($percentage >= 40) return 'D';
    return 'F';
}
?>
