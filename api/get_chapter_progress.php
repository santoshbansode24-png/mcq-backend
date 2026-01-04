<?php
/**
 * Get Chapter Progress API
 * Veeru
 * 
 * Endpoint: GET /api/get_chapter_progress.php?user_id=1&subject_id=2
 * Purpose: Get detailed chapter-wise progress for a subject including solved count, mistakes, and completion percentage
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Validate inputs
if ($user_id <= 0) {
    sendResponse('error', 'Valid user_id is required', null, 400);
}

if ($subject_id <= 0) {
    sendResponse('error', 'Valid subject_id is required', null, 400);
}

try {
    // Get all chapters for the subject with progress data
    $sql = "
        SELECT 
            c.chapter_id,
            c.chapter_name,
            c.subject_id,
            
            -- Total MCQs in chapter
            (SELECT COUNT(*) FROM mcqs WHERE chapter_id = c.chapter_id) as total_mcqs,
            
            -- Unique MCQs solved (attempted at least once)
            (SELECT COUNT(DISTINCT mcq_id) 
             FROM mcq_attempts 
             WHERE user_id = ? AND chapter_id = c.chapter_id) as solved_mcqs,
            
            -- Mistakes count (unique MCQs answered incorrectly at least once)
            (SELECT COUNT(DISTINCT mcq_id) 
             FROM mcq_attempts 
             WHERE user_id = ? AND chapter_id = c.chapter_id AND is_correct = 0) as mistakes_count,
            
            -- Best score from student_progress (for backward compatibility)
            (SELECT MAX(percentage) 
             FROM student_progress 
             WHERE user_id = ? AND chapter_id = c.chapter_id) as best_score
            
        FROM chapters c
        WHERE c.subject_id = ?
        ORDER BY c.chapter_id ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id, $user_id, $subject_id]);
    $chapters = $stmt->fetchAll();
    
    // Calculate progress for each chapter
    $progress_data = [];
    $summary = [
        'total_chapters' => count($chapters),
        'completed' => 0,
        'in_progress' => 0,
        'not_started' => 0
    ];
    
    foreach ($chapters as $chapter) {
        $total = intval($chapter['total_mcqs']);
        $solved = intval($chapter['solved_mcqs']);
        $remaining = max(0, $total - $solved);
        $percentage = $total > 0 ? round(($solved / $total) * 100, 1) : 0;
        
        // Determine status
        $status = 'not_started';
        if ($percentage >= 100) {
            $status = 'completed';
            $summary['completed']++;
        } elseif ($percentage > 0) {
            $status = 'in_progress';
            $summary['in_progress']++;
        } else {
            $summary['not_started']++;
        }
        
        $progress_data[] = [
            'chapter_id' => intval($chapter['chapter_id']),
            'chapter_name' => $chapter['chapter_name'],
            'total_mcqs' => $total,
            'solved_mcqs' => $solved,
            'remaining_mcqs' => $remaining,
            'mistakes_count' => intval($chapter['mistakes_count']),
            'completion_percentage' => $percentage,
            'status' => $status,
            'best_score' => $chapter['best_score'] ? floatval($chapter['best_score']) : null
        ];
    }
    
    // Success response
    sendResponse('success', 'Chapter progress retrieved successfully', [
        'chapters' => $progress_data,
        'summary' => $summary
    ], 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
