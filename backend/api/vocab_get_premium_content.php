<?php
/**
 * Get Premium Content API
 * Returns premium vocabulary words for a specific category
 * 
 * Endpoint: GET /api/vocab_get_premium_content.php?user_id=X&category_id=Y&limit=50
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/db.php';

try {
    // Get parameters
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    // Validate inputs
    if ($userId <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Valid user_id is required'
        ]);
        exit;
    }
    
    // Check user's premium access
    $sql = "SELECT has_premium_access, premium_expiry_date 
            FROM user_vocab_stats 
            WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $userAccess = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $hasPremium = $userAccess && $userAccess['has_premium_access'] && 
                  ($userAccess['premium_expiry_date'] === null || 
                   $userAccess['premium_expiry_date'] >= date('Y-m-d'));
    
    // Build query based on access level
    $accessCondition = $hasPremium ? "" : "AND vc.access_level = 'Free'";
    $categoryCondition = $categoryId > 0 ? "AND vw.category_id = :category_id" : "";
    
    $sql = "SELECT 
                vw.word_id,
                vw.word,
                vw.definition,
                vw.example_sentence,
                vw.pronunciation_text,
                vw.difficulty_level,
                vw.word_type,
                vw.synonyms,
                vw.antonyms,
                vw.mnemonic_hint,
                vc.category_name,
                vc.access_level,
                CASE WHEN uvp.word_id IS NOT NULL THEN TRUE ELSE FALSE END as is_learning
            FROM vocab_words vw
            JOIN vocab_categories vc ON vw.category_id = vc.category_id
            LEFT JOIN user_vocab_progress uvp ON vw.word_id = uvp.word_id AND uvp.user_id = :user_id
            WHERE vw.is_active = TRUE
            $accessCondition
            $categoryCondition
            ORDER BY vw.difficulty_level, vw.word
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($categoryId > 0) {
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available categories
    $sql = "SELECT category_id, category_name, access_level, icon_emoji, word_count
            FROM vocab_categories
            WHERE is_active = TRUE
            $accessCondition
            ORDER BY display_order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'words' => $words,
            'categories' => $categories,
            'has_premium_access' => $hasPremium,
            'total_words' => count($words)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch content: ' . $e->getMessage()
    ]);
}

