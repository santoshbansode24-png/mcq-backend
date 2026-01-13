<?php
/**
 * Get Set Status API
 * Veeru
 * 
 * Endpoint: GET /api/get_set_status.php?user_id=1&chapter_id=1&type=mcq
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'mcq'; // 'mcq' or 'flashcard'

if ($user_id <= 0 || $chapter_id <= 0) {
    sendResponse('error', 'Invalid params', [], 400);
}

try {
    $results = [];

    if ($type === 'flashcard') {
        // Fetch specific completed sets
        $stmt = $pdo->prepare("SELECT set_index FROM flashcard_progress WHERE user_id = ? AND chapter_id = ?");
        $stmt->execute([$user_id, $chapter_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Return a map for easy lookup: { "0": true, "2": true }
        foreach($rows as $idx) {
            $results[$idx] = ['status' => 'completed'];
        }

    } else {
        // MCQ Logic: Dynamic Calculation
        // 1. Get ALL MCQs in stable order (must match get_mcqs.php)
        $mcqStmt = $pdo->prepare("SELECT mcq_id, correct_answer FROM mcqs WHERE chapter_id = ? ORDER BY mcq_id ASC");
        $mcqStmt->execute([$chapter_id]);
        $allMcqs = $mcqStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Get ALL attempts for this user & chapter
        // We want the BEST attempt (or latest). Let's just key by mcq_id and check correctness.
        // Actually, we process attempts to see if they EVER got it right, or just attempted it.
        // "Solved" usually means "attempted". "Scored" means "correct".
        // Let's fetch all unique attempts.
        $attStmt = $pdo->prepare("SELECT mcq_id, is_correct FROM mcq_attempts WHERE user_id = ? AND chapter_id = ?");
        $attStmt->execute([$user_id, $chapter_id]);
        $attempts = $attStmt->fetchAll(PDO::FETCH_ASSOC);

        $attemptMap = [];
        foreach($attempts as $att) {
            $mid = $att['mcq_id'];
            if (!isset($attemptMap[$mid])) {
                $attemptMap[$mid] = ['attempted' => true, 'correct' => false];
            }
            if ($att['is_correct']) {
                $attemptMap[$mid]['correct'] = true;
            }
        }

        // 3. Chunk into sets of 10
        $chunkSize = 10;
        $totalSets = ceil(count($allMcqs) / $chunkSize);

        for ($i = 0; $i < $totalSets; $i++) {
            $slice = array_slice($allMcqs, $i * $chunkSize, $chunkSize);
            
            $setAttempts = 0;
            $setCorrect = 0;
            $totalInSet = count($slice);

            foreach ($slice as $q) {
                if (isset($attemptMap[$q['mcq_id']])) {
                    $setAttempts++;
                    if ($attemptMap[$q['mcq_id']]['correct']) {
                        $setCorrect++;
                    }
                }
            }

            // Define "Completed"
            // Option A: Attempted ALL questions (Completion based)
            // Option B: Scored > X% (Performance based)
            // User asked: "Shows user solve set 1". Usually means attempted all.
            
            $status = 'not_started';
            if ($setAttempts == $totalInSet && $totalInSet > 0) {
                $status = 'completed';
            } elseif ($setAttempts > 0) {
                $status = 'in_progress';
            }

            $results[$i] = [
                'status' => $status,
                'score' => $setCorrect,
                'total' => $totalInSet
            ];
        }
    }

    sendResponse('success', 'Status fetched', $results);

} catch (PDOException $e) {
    sendResponse('error', $e->getMessage(), null, 500);
}
?>
