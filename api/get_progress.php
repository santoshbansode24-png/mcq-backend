<?php
/**
 * Get Student Progress API
 * Veeru
 * 
 * Endpoint: GET /api/get_progress.php?user_id=1
 * Purpose: Get student's quiz progress and statistics
 */

require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get user_id from query parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Validate user_id
if ($user_id <= 0) {
    sendResponse('error', 'Valid user_id is required', null, 400);
}

try {
    // Get overall statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_attempts,
            AVG(percentage) as average_percentage,
            MAX(percentage) as best_percentage,
            SUM(mcq_score) as total_correct,
            SUM(total_mcq) as total_questions
        FROM student_progress
        WHERE user_id = ?
    ");
    $statsStmt->execute([$user_id]);
    $stats = $statsStmt->fetch();
    
    // Get recent progress
    $progressStmt = $pdo->prepare("
        SELECT 
            sp.progress_id,
            sp.chapter_id,
            sp.mcq_score,
            sp.total_mcq,
            sp.percentage,
            sp.completed_at,
            ch.chapter_name,
            s.subject_name,
            c.class_name
        FROM student_progress sp
        INNER JOIN chapters ch ON sp.chapter_id = ch.chapter_id
        INNER JOIN subjects s ON ch.subject_id = s.subject_id
        INNER JOIN classes c ON s.class_id = c.class_id
        WHERE sp.user_id = ?
        ORDER BY sp.completed_at DESC
        LIMIT 20
    ");
    $progressStmt->execute([$user_id]);
    $recentProgress = $progressStmt->fetchAll();
    
    // Get chapter-wise best scores
    $chapterStmt = $pdo->prepare("
        SELECT 
            sp.chapter_id,
            ch.chapter_name,
            s.subject_name,
            MAX(sp.percentage) as best_percentage,
            COUNT(*) as attempts
        FROM student_progress sp
        INNER JOIN chapters ch ON sp.chapter_id = ch.chapter_id
        INNER JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE sp.user_id = ?
        GROUP BY sp.chapter_id, ch.chapter_name, s.subject_name
        ORDER BY s.subject_name, ch.chapter_name
    ");
    $chapterStmt->execute([$user_id]);
    $chapterProgress = $chapterStmt->fetchAll();
    
    // Prepare response
    $responseData = [
        'overall_stats' => $stats,
        'recent_progress' => $recentProgress,
        'chapter_progress' => $chapterProgress
    ];
    
    // Success response
    sendResponse('success', 'Progress retrieved successfully', $responseData, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
