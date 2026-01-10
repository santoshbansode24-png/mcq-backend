<?php
/**
 * Add New Word API
 * Adds a word to user's learning list
 * 
 * Endpoint: POST /api/vocab_add_new_word.php
 * Body: { user_id, word_id }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db.php';
require_once '../services/SRSService.php';

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['user_id']) || !isset($data['word_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'user_id and word_id are required'
        ]);
        exit;
    }
    
    $userId = intval($data['user_id']);
    $wordId = intval($data['word_id']);
    
    // Validate inputs
    if ($userId <= 0 || $wordId <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid user_id or word_id'
        ]);
        exit;
    }
    
    // Initialize SRS Service
    $srsService = new SRSService($pdo);
    
    // Add word
    $result = $srsService->addNewWord($userId, $wordId);
    
    if ($result['success']) {
        // Get word details
        $sql = "SELECT vw.word, vw.definition, vw.example_sentence, 
                       vw.difficulty_level, vc.category_name
                FROM vocab_words vw
                JOIN vocab_categories vc ON vw.category_id = vc.category_id
                WHERE vw.word_id = :word_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':word_id' => $wordId]);
        $wordDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $wordDetails
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to add word: ' . $e->getMessage()
    ]);
}

