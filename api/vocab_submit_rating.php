<?php
/**
 * Submit Rating API
 * Updates SRS progress based on user's self-rating
 * * Endpoint: POST /api/vocab_submit_rating.php
 */

// 1. Optimization: Start Output Buffering
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';
require_once '../services/SRSService.php';

try {
    // 2. Optimization: Secure Input Handling
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Strict Type Casting & Validation
    $userId = filter_var($input['user_id'] ?? 0, FILTER_VALIDATE_INT);
    $wordId = filter_var($input['word_id'] ?? 0, FILTER_VALIDATE_INT);
    $rating = filter_var($input['rating'] ?? 0, FILTER_VALIDATE_INT);
    $timeTaken = filter_var($input['time_taken_seconds'] ?? 0, FILTER_VALIDATE_INT);
    
    // Validation
    if (!$userId || !$wordId || !$rating) {
        http_response_code(400); // Bad Request
        throw new Exception('user_id, word_id, and rating are required');
    }
    
    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        throw new Exception('Rating must be between 1 and 5');
    }
    
    // 3. Optimization: Transaction for Data Integrity
    $pdo->beginTransaction();
    
    // Initialize SRS Service & Update Progress
    $srsService = new SRSService($pdo);
    $result = $srsService->updateProgress($userId, $wordId, $rating, $timeTaken);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    // Fetch Word & Set Info (Single efficient query)
    $sql = "SELECT vw.word, vw.definition, vw.set_number, vc.category_name
            FROM vocab_words vw
            JOIN vocab_categories vc ON vw.category_id = vc.category_id
            WHERE vw.word_id = :word_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':word_id' => $wordId]);
    $wordDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate Set Progress
    $setNumber = (int)$wordDetails['set_number'];
    
    $sql = "SELECT 
                COUNT(*) as total_words,
                SUM(CASE WHEN uvp.mastery_status = 'Mastered' THEN 1 ELSE 0 END) as mastered_words
            FROM vocab_words vw
            LEFT JOIN user_vocab_progress uvp ON uvp.word_id = vw.word_id AND uvp.user_id = :user_id
            WHERE vw.set_number = :set_number";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId, ':set_number' => $setNumber]);
    $setProgress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Safe Percentage Calculation (Avoid Div/0)
    $totalWords = (int)$setProgress['total_words'];
    $masteredWords = (int)$setProgress['mastered_words'];
    $completionPercentage = ($totalWords > 0) ? ($masteredWords / $totalWords) * 100 : 0;
    
    // Check for Set Completion (70% Threshold)
    if ($completionPercentage >= 70) {
        $nextSet = $setNumber + 1;
        
        // 4. Optimization: Logic Update for "Unlocking"
        // Use GREATEST() to ensure we don't accidentally re-lock a set if they replay an old one.
        // We update 'highest_set_unlocked' regardless of what 'current_set' is.
        $sql = "UPDATE user_vocab_stats 
                SET highest_set_unlocked = GREATEST(highest_set_unlocked, :next_set),
                    sets_completed = GREATEST(sets_completed, :completed_set)
                WHERE user_id = :user_id";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':next_set' => $nextSet,
            ':completed_set' => $setNumber
        ]);
    }
    
    $pdo->commit(); // Commit all changes
    
    // 5. Optimization: Clean Output
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Progress updated successfully',
        'data' => [
            'word' => $wordDetails['word'],
            'category' => $wordDetails['category_name'],
            'easiness_factor' => $result['easiness_factor'],
            'interval_days' => $result['interval_days'],
            'next_review_date' => $result['next_review_date'],
            'mastery_status' => $result['mastery_status'],
            'review_count' => $result['review_count'],
            'accuracy' => $result['accuracy'],
            'set_completed' => ($completionPercentage >= 70),
            'completion_percentage' => round($completionPercentage)
        ]
    ], JSON_NUMERIC_CHECK);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    // Default to 500 error unless it was a validation error (400)
    if (http_response_code() === 200) http_response_code(500);
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed: ' . $e->getMessage()
    ]);
}
?>