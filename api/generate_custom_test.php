<?php
/**
 * Generate Custom Test API
 * Veeru
 * 
 * Endpoint: POST /api/generate_custom_test.php
 * Input: { "chapter_ids": "1,2,3", "limit": 25 }
 * Purpose: Generate a random test from selected chapters
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON Input
$data = getJsonInput();

// Validate Input
$chapter_ids_str = isset($data['chapter_ids']) ? $data['chapter_ids'] : '';
$limit = isset($data['limit']) ? intval($data['limit']) : 20;

if (empty($chapter_ids_str)) {
    sendResponse('error', 'No chapters selected', null, 400);
}

// Security: Clean and validate IDs
$ids_array = explode(',', $chapter_ids_str);
$clean_ids = [];
foreach ($ids_array as $id) {
    if (is_numeric($id) && $id > 0) {
        $clean_ids[] = intval($id);
    }
}

if (empty($clean_ids)) {
    sendResponse('error', 'Invalid chapter IDs provided', null, 400);
}

// Create placeholders for prepared statement (e.g., ?,?,?)
$placeholders = implode(',', array_fill(0, count($clean_ids), '?'));

try {
    // 1. Fetch only IDs from the selected chapters (Very Fast)
    $idSql = "SELECT mcq_id FROM mcqs WHERE chapter_id IN ($placeholders)";
    $idStmt = $pdo->prepare($idSql);
    $idStmt->execute($clean_ids);
    $allIds = $idStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allIds)) {
        sendResponse('error', 'No questions found for the selected chapters', null, 404);
    }

    // 2. Shuffle the IDs in PHP and pick the amount we need (Zero DB Load)
    shuffle($allIds);
    $selectedIds = array_slice($allIds, 0, $limit);

    // 3. Fetch the full questions for ONLY the randomly selected IDs
    $idPlaceholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $sql = "
        SELECT 
            mcq_id,
            chapter_id,
            question,
            option_a,
            option_b,
            option_c,
            option_d,
            correct_answer,
            explanation
        FROM mcqs
        WHERE mcq_id IN ($idPlaceholders)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($selectedIds);
    $mcqs = $stmt->fetchAll();
    
    // Shuffle the final output array so they aren't ordered by ID
    shuffle($mcqs);

    if (empty($mcqs)) {
        sendResponse('error', 'Failed to retrieve selected questions', null, 500);
    }
    
    // Decode HTML entities and sanitize
    foreach ($mcqs as &$mcq) {
        $mcq['question'] = html_entity_decode($mcq['question']);
        $mcq['option_a'] = html_entity_decode($mcq['option_a']);
        $mcq['option_b'] = html_entity_decode($mcq['option_b']);
        $mcq['option_c'] = html_entity_decode($mcq['option_c']);
        $mcq['option_d'] = html_entity_decode($mcq['option_d']);
        $mcq['explanation'] = html_entity_decode($mcq['explanation']);
        $mcq['correct_answer'] = strtolower($mcq['correct_answer']);
    }

    sendResponse('success', 'Custom test generated successfully', $mcqs, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
