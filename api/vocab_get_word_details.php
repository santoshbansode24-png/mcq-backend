<?php
/**
 * Get Word Details API
 * Returns detailed information about a specific word
 * Endpoint: GET /api/vocab_get_word_details.php?word_id=X&user_id=Y
 */

// 1. Optimization: Start Output Buffering
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/db.php';

try {
    // 2. Optimization: Secure Input Handling
    $wordId = filter_input(INPUT_GET, 'word_id', FILTER_VALIDATE_INT);
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    
    if (!$wordId || $wordId <= 0) {
        http_response_code(400); // Bad Request
        throw new Exception('Valid word_id is required');
    }
    
    // Fetch Word Details + Category Name (Joined)
    $sql = "SELECT 
                vw.*,
                vc.category_name,
                vc.access_level
            FROM vocab_words vw
            JOIN vocab_categories vc ON vw.category_id = vc.category_id
            WHERE vw.word_id = :word_id";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':word_id' => $wordId]);
    $word = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$word) {
        http_response_code(404); // Not Found
        throw new Exception('Word not found');
    }

    // 3. Optimization: Auto-decode 'options' if stored as JSON string
    if (isset($word['options']) && is_string($word['options'])) {
        $decoded = json_decode($word['options'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $word['options'] = $decoded;
        }
    }
    
    // Fetch User Progress (Only if user_id is provided)
    $userProgress = null;
    if ($userId && $userId > 0) {
        $sql = "SELECT mastery_status, review_count, correct_count, next_review_date 
                FROM user_vocab_progress 
                WHERE user_id = :user_id AND word_id = :word_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':word_id' => $wordId]);
        $userProgress = $stmt->fetch(PDO::FETCH_ASSOC); // Returns false (null) if no record
    }
    
    // Fetch Related MCQs (Sorted by relevance)
    $sql = "SELECT 
                m.mcq_id,
                m.question,
                m.difficulty_level,
                mvl.relevance_score
            FROM mcq_vocab_link mvl
            JOIN mcqs m ON mvl.mcq_id = m.mcq_id
            WHERE mvl.word_id = :word_id
            ORDER BY mvl.relevance_score DESC
            LIMIT 5";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':word_id' => $wordId]);
    $relatedMCQs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Optimization: Clean Output Buffer & Numeric Check
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'data' => [
            'word' => $word,
            'user_progress' => $userProgress ?: null, // Ensures null if false
            'related_mcqs' => $relatedMCQs
        ]
    ], JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_clean();
    // Determine error code (default to 500 if not set previously)
    if (http_response_code() === 200) http_response_code(500);
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed: ' . $e->getMessage()
    ]);
}
?>