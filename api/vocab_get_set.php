<?php
/**
 * Get Vocab Set API
 * Returns 25 words for a specific set with user progress
 * * Endpoint: GET /api/vocab_get_set.php?user_id=X&set_number=1
 */

// 1. Optimization: Start Output Buffering to prevent whitespace corruption
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/db.php';

try {
    // 2. Optimization: Cleaner Input Validation
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    $setNumber = filter_input(INPUT_GET, 'set_number', FILTER_VALIDATE_INT);
    
    if (!$userId) {
        http_response_code(400); // Bad Request
        throw new Exception('Valid user_id is required');
    }
    
    // Get user's stats
    $sql = "SELECT current_set, sets_completed, highest_set_unlocked 
            FROM user_vocab_stats 
            WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize if no stats exist
    if (!$userStats) {
        // Optimization: Use INSERT IGNORE to prevent race conditions
        $sql = "INSERT IGNORE INTO user_vocab_stats (user_id, current_set, highest_set_unlocked) 
                VALUES (:user_id, 1, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        $userStats = ['current_set' => 1, 'sets_completed' => 0, 'highest_set_unlocked' => 1];
    }
    
    // Default to current set if not specified
    if (!$setNumber || $setNumber <= 0) {
        $setNumber = $userStats['current_set'];
    }
    
    // 3. Optimization: COALESCE handles nulls in SQL directly
    $sql = "SELECT 
                vw.word_id,
                vw.word,
                vw.definition,
                vw.definition_marathi,
                vw.example_sentence,
                vw.difficulty_level,
                vw.set_number,
                vw.level_name,
                vw.word_type,
                vw.synonyms,
                vw.antonyms,
                vw.options,
                vw.correct_answer,
                COALESCE(uvp.mastery_status, 'New') as mastery_status,
                COALESCE(uvp.review_count, 0) as review_count,
                COALESCE(uvp.correct_count, 0) as correct_count
            FROM vocab_words vw
            LEFT JOIN user_vocab_progress uvp ON uvp.word_id = vw.word_id AND uvp.user_id = :user_id
            WHERE vw.set_number = :set_number
            ORDER BY vw.word_id ASC
            LIMIT 25"; // Fixed: Updated to 25 to match docblock
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':set_number' => $setNumber
    ]);
    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process words
    $totalWords = count($words);
    $masteredWords = 0;
    $reviewedWords = 0;
    
    foreach ($words as &$word) {
        if ($word['mastery_status'] === 'Mastered') {
            $masteredWords++;
        }
        if ($word['review_count'] > 0) {
            $reviewedWords++;
        }
        
        // Decode options safely
        if (!empty($word['options']) && is_string($word['options'])) {
            $decoded = json_decode($word['options'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $word['options'] = $decoded;
            }
        }
    }
    unset($word); // Break reference
    
    $completionPercentage = $totalWords > 0 ? round(($masteredWords / $totalWords) * 100) : 0;
    
    // Prepare Response
    $response = [
        'status' => 'success',
        'data' => [
            'set_number' => $setNumber,
            'level_name' => $words[0]['level_name'] ?? 'General',
            'words' => $words,
            'total_words' => $totalWords,
            'mastered_words' => $masteredWords,
            'reviewed_words' => $reviewedWords,
            'completion_percentage' => $completionPercentage,
            'is_completed' => ($completionPercentage >= 80),
            'user_stats' => [
                'current_set' => (int)$userStats['current_set'],
                'sets_completed' => (int)$userStats['sets_completed'],
                'highest_set_unlocked' => (int)$userStats['highest_set_unlocked'],
                'total_sets' => 200
            ]
        ]
    ];

    // 4. Output: Clean buffer and output with numeric check
    ob_clean();
    echo json_encode($response, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500); // Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch set: ' . $e->getMessage()
    ]);
}
?>