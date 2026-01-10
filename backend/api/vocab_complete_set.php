<?php
/**
 * Complete Vocab Set API
 * Marks a set as completed and unlocks the next set
 * * Endpoint: POST /api/vocab_complete_set.php
 */

// 1. Optimization: Output Buffering for clean JSON
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';

try {
    // 2. Optimization: Efficient Input Reading
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Strict Type Casting (Security)
    $userId = filter_var($input['user_id'] ?? 0, FILTER_VALIDATE_INT);
    $setNumber = filter_var($input['set_number'] ?? 0, FILTER_VALIDATE_INT);
    $score = filter_var($input['score'] ?? 0, FILTER_VALIDATE_INT);
    $totalQuestions = filter_var($input['total_questions'] ?? 10, FILTER_VALIDATE_INT);
    
    if (!$userId || !$setNumber) {
        http_response_code(400);
        throw new Exception('user_id and set_number are required');
    }
    
    // Calculate percentage
    // Prevent division by zero
    $totalQuestions = ($totalQuestions > 0) ? $totalQuestions : 10;
    $percentage = ($score / $totalQuestions) * 100;
    $requiredPercentage = 70;
    
    // Check Pass/Fail
    if ($percentage < $requiredPercentage) {
        ob_clean();
        echo json_encode([
            'status' => 'error',
            'message' => "Need $requiredPercentage% to unlock next set",
            'score' => $score,
            'total' => $totalQuestions,
            'percentage' => round($percentage),
            'required' => $requiredPercentage
        ], JSON_NUMERIC_CHECK);
        exit;
    }
    
    // 3. Optimization: Database Transaction for Integrity
    $pdo->beginTransaction();

    // Logic: Only unlock up to max 200 sets
    $nextSet = min($setNumber + 1, 200); 
    
    // Award XP based on performance
    $xpEarned = 100; 
    if ($percentage >= 90) $xpEarned = 150;
    elseif ($percentage >= 80) $xpEarned = 125;
    
    // 4. Optimization: Single Smart Query
    // Removed the redundant "SELECT" before this. 
    // GREATEST() ensures we never downgrade progress if a user replays an old set.
    $sql = "UPDATE user_vocab_stats 
            SET current_set = GREATEST(current_set, :next_current_set),
                sets_completed = GREATEST(sets_completed, :set_number),
                highest_set_unlocked = GREATEST(highest_set_unlocked, :next_highest_set),
                experience_points = experience_points + :xp_earned
            WHERE user_id = :user_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':next_current_set' => $nextSet,
        ':set_number' => $setNumber,
        ':next_highest_set' => $nextSet, // Bind same value to second placeholder
        ':xp_earned' => $xpEarned
    ]);
    
    // Fetch final stats to return to app
    $sql = "SELECT current_set, sets_completed, highest_set_unlocked, experience_points 
            FROM user_vocab_stats 
            WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit(); // Commit changes
    
    // 5. Optimization: Clean Output
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'message' => ($nextSet <= 200) ? 'Set completed! Next set unlocked.' : 'All sets completed!',
        'data' => [
            'completed_set' => $setNumber,
            'next_set' => $nextSet,
            'sets_completed' => (int)$stats['sets_completed'],
            'highest_set_unlocked' => (int)$stats['highest_set_unlocked'],
            'experience_earned' => $xpEarned,
            'total_experience' => (int)$stats['experience_points'],
            'score' => $score,
            'percentage' => round($percentage)
        ]
    ], JSON_NUMERIC_CHECK);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed: ' . $e->getMessage()
    ]);
}
?>