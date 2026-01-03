<?php
/**
 * Get Review List API
 * Returns words due for review today for a specific user
 * 
 * Endpoint: GET /api/vocab_get_review_list.php?user_id=X&limit=20
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/db.php';
require_once '../services/SRSService.php';

try {
    // Get parameters
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    
    // Validate user ID
    if ($userId <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Valid user_id is required'
        ]);
        exit;
    }
    
    // Validate limit
    if ($limit < 1 || $limit > 100) {
        $limit = 20; // Default to 20
    }
    
    // Initialize SRS Service
    $srsService = new SRSService($pdo);
    
    // Get due words
    $dueWords = $srsService->getDueWords($userId, $limit);
    
    // Get user stats for context
    $sql = "SELECT 
                total_words_learned,
                words_mastered,
                current_streak,
                longest_streak,
                total_reviews,
                accuracy_percentage
            FROM user_vocab_stats
            WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no stats exist, create default
    if (!$userStats) {
        $userStats = [
            'total_words_learned' => 0,
            'words_mastered' => 0,
            'current_streak' => 0,
            'longest_streak' => 0,
            'total_reviews' => 0,
            'accuracy_percentage' => 0
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'due_words' => $dueWords,
            'total_due' => count($dueWords),
            'user_stats' => $userStats
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch review list: ' . $e->getMessage()
    ]);
}

