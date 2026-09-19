<?php
/**
 * Get MCQs API (Multi-Language Supported)
 * Veeru
 * 
 * Endpoint: GET /api/get_mcqs.php?chapter_id=1&lang=mr
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get parameters
$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$chapter_ids = isset($_GET['chapter_ids']) ? $_GET['chapter_ids'] : '';
$lang = strtolower(trim($_GET['lang'] ?? $_GET['language'] ?? 'en'));

// Validate
if ($chapter_id <= 0 && empty($chapter_ids)) {
    sendResponse('error', 'Valid chapter_id or chapter_ids is required', null, 400);
}

try {
    if (!empty($chapter_ids)) {
        $ids_array = array_filter(array_map('intval', explode(',', $chapter_ids)));
        if (empty($ids_array)) {
            sendResponse('error', 'Invalid chapter_ids format', null, 400);
        }
        $inQuery = implode(',', array_fill(0, count($ids_array), '?'));
        
        $stmt = $pdo->prepare("
            SELECT * FROM mcqs
            WHERE chapter_id IN ($inQuery)
            ORDER BY mcq_id ASC
        ");
        $stmt->execute(array_values($ids_array));
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM mcqs
            WHERE chapter_id = ?
            ORDER BY mcq_id ASC
        ");
        $stmt->execute([$chapter_id]);
    }
    
    $rows = $stmt->fetchAll();
    
    $mcqs = [];
    foreach ($rows as $row) {
        $mcq = [
            'mcq_id' => $row['mcq_id'],
            'chapter_id' => $row['chapter_id'],
            'question' => $row['question'],
            'option_a' => $row['option_a'],
            'option_b' => $row['option_b'],
            'option_c' => $row['option_c'],
            'option_d' => $row['option_d'],
            'correct_answer' => $row['correct_answer'],
            'explanation' => $row['explanation'],
            'difficulty' => $row['difficulty']
        ];

        // Apply Language Translation if requested & available
        if ($lang === 'hi') {
            if (!empty($row['question_hi'])) $mcq['question'] = $row['question_hi'];
            if (!empty($row['option_a_hi'])) $mcq['option_a'] = $row['option_a_hi'];
            if (!empty($row['option_b_hi'])) $mcq['option_b'] = $row['option_b_hi'];
            if (!empty($row['option_c_hi'])) $mcq['option_c'] = $row['option_c_hi'];
            if (!empty($row['option_d_hi'])) $mcq['option_d'] = $row['option_d_hi'];
            if (!empty($row['explanation_hi'])) $mcq['explanation'] = $row['explanation_hi'];
        } elseif ($lang === 'mr') {
            if (!empty($row['question_mr'])) $mcq['question'] = $row['question_mr'];
            if (!empty($row['option_a_mr'])) $mcq['option_a'] = $row['option_a_mr'];
            if (!empty($row['option_b_mr'])) $mcq['option_b'] = $row['option_b_mr'];
            if (!empty($row['option_c_mr'])) $mcq['option_c'] = $row['option_c_mr'];
            if (!empty($row['option_d_mr'])) $mcq['option_d'] = $row['option_d_mr'];
            if (!empty($row['explanation_mr'])) $mcq['explanation'] = $row['explanation_mr'];
        }

        $mcqs[] = $mcq;
    }
    
    sendResponse('success', 'MCQs retrieved successfully', $mcqs, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
