<?php
/**
 * Get Chapter Progress API
 * Veeru
 * 
 * Endpoint: GET /api/get_chapter_progress.php?user_id=1&subject_id=2
 * Purpose: Get detailed chapter-wise progress for a subject using optimized batch queries
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
    // 1. Fetch all chapters for the subject
    $stmtChapters = $pdo->prepare("
        SELECT 
            chapter_id, 
            chapter_name, 
            subject_id
        FROM chapters 
        WHERE subject_id = ? 
        ORDER BY chapter_order ASC, chapter_id ASC
    ");
    $stmtChapters->execute([$subject_id]);
    $chapters = $stmtChapters->fetchAll(PDO::FETCH_ASSOC);

    if (empty($chapters)) {
        sendResponse('success', 'No chapters found', ['chapters' => [], 'summary' => ['total_chapters' => 0, 'completed' => 0, 'in_progress' => 0, 'not_started' => 0]], 200);
    }

    // Extract chapter IDs for efficient querying
    $chapterIds = array_column($chapters, 'chapter_id');
    $placeholders = implode(',', array_fill(0, count($chapterIds), '?'));

    // 2. Fetch total MCQs per chapter in one go
    // Note: We use the same placeholders array because we are querying by chapter_id IN (...)
    $stmtTotalMcqs = $pdo->prepare("
        SELECT chapter_id, COUNT(*) as total_mcqs 
        FROM mcqs 
        WHERE chapter_id IN ($placeholders)
        GROUP BY chapter_id
    ");
    $stmtTotalMcqs->execute($chapterIds);
    $totalMcqsMap = $stmtTotalMcqs->fetchAll(PDO::FETCH_KEY_PAIR); // [chapter_id => total_mcqs]

    // 3. Fetch user attempts (solved and mistakes) in one go
    // We group by chapter_id and calculate solved/mistakes using conditional aggregation
    $stmtAttempts = $pdo->prepare("
        SELECT 
            chapter_id,
            COUNT(DISTINCT mcq_id) as solved_mcqs,
            COUNT(DISTINCT CASE WHEN is_correct = 0 THEN mcq_id END) as mistakes_count
        FROM mcq_attempts 
        WHERE user_id = ? AND chapter_id IN ($placeholders)
        GROUP BY chapter_id
    ");
    // Merge user_id with chapterIds for the parameters
    $stmtAttempts->execute(array_merge([$user_id], $chapterIds));
    $attemptsMap = $stmtAttempts->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    // 4. Fetch best scores from student_progress (legacy/backup)
    $stmtScores = $pdo->prepare("
        SELECT chapter_id, MAX(percentage) as best_score 
        FROM student_progress 
        WHERE user_id = ? AND chapter_id IN ($placeholders)
        GROUP BY chapter_id
    ");
    $stmtScores->execute(array_merge([$user_id], $chapterIds));
    $scoresMap = $stmtScores->fetchAll(PDO::FETCH_KEY_PAIR);

    // Process and assemble logic
    $progress_data = [];
    $summary = [
        'total_chapters' => count($chapters),
        'completed' => 0,
        'in_progress' => 0,
        'not_started' => 0
    ];

    foreach ($chapters as $chapter) {
        $chId = $chapter['chapter_id'];
        
        $total = isset($totalMcqsMap[$chId]) ? intval($totalMcqsMap[$chId]) : 0;
        
        // Get attempt data if exists
        $solved = 0;
        $mistakes = 0;
        if (isset($attemptsMap[$chId])) {
            $solved = intval($attemptsMap[$chId]['solved_mcqs']);
            $mistakes = intval($attemptsMap[$chId]['mistakes_count']);
        }

        // Get legacy score if exists
        $bestScore = isset($scoresMap[$chId]) ? floatval($scoresMap[$chId]) : null;

        $remaining = max(0, $total - $solved);
        $percentage = $total > 0 ? round(($solved / $total) * 100, 1) : 0;
        
        // Determine status
        $status = 'not_started';
        if ($percentage >= 100) {
            // Check if effectively completed (sometimes slight calc diffs)
            $status = 'completed';
            $summary['completed']++;
        } elseif ($percentage > 0) {
            $status = 'in_progress';
            $summary['in_progress']++;
        } else {
            $summary['not_started']++;
        }
        
        $progress_data[] = [
            'chapter_id' => intval($chId),
            'chapter_name' => $chapter['chapter_name'],
            'total_mcqs' => $total,
            'solved_mcqs' => $solved,
            'remaining_mcqs' => $remaining,
            'mistakes_count' => $mistakes,
            'completion_percentage' => $percentage,
            'status' => $status,
            'best_score' => $bestScore
        ];
    }
    
    // Success response
    sendResponse('success', 'Chapter progress retrieved successfully', [
        'chapters' => $progress_data,
        'summary' => $summary
    ], 200);
    
} catch (PDOException $e) {
    // Log error for debugging but don't expose sensitive DB info to user
    error_log("Database Error in get_chapter_progress: " . $e->getMessage());
    sendResponse('error', 'Database error occurred', null, 500);
}
?>
