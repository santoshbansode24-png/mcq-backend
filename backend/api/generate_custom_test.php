<?php
/**
 * Generate Custom Test API
 * Veeru
 *
 * Endpoint: POST /api/generate_custom_test.php
 * Input: { "chapter_ids": "1,2,3", "limit": 25 }
 * Purpose: Generate a random test from selected chapters (optimized)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$data = getJsonInput();

$chapter_ids_str = isset($data['chapter_ids']) ? $data['chapter_ids'] : '';
$limit = isset($data['limit']) ? min(intval($data['limit']), 200) : 20;

if (empty($chapter_ids_str)) {
    sendResponse('error', 'No chapters selected', null, 400);
}

// Security: clean and validate IDs
$ids_array = explode(',', $chapter_ids_str);
$clean_ids = [];
foreach ($ids_array as $id) {
    $id = trim($id);
    if (is_numeric($id) && $id > 0) {
        $clean_ids[] = intval($id);
    }
}

if (empty($clean_ids)) {
    sendResponse('error', 'Invalid chapter IDs provided', null, 400);
}

$placeholders = implode(',', array_fill(0, count($clean_ids), '?'));

try {
    // Step 1: Get total count — fast index scan, no ORDER BY RAND()
    $countSql = "SELECT COUNT(*) FROM mcqs WHERE chapter_id IN ($placeholders)";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($clean_ids);
    $total = (int)$countStmt->fetchColumn();

    if ($total === 0) {
        sendResponse('error', 'No questions found for the selected chapters', null, 404);
    }

    // Step 2: If asking for all (or more than available), just fetch all — no shuffling needed server-side
    // Otherwise, use a fast keyset-based random sampling:
    // Fetch $limit MCQs by selecting random offsets using a subquery trick.
    // This is much faster than ORDER BY RAND() on large tables.
    $fetchLimit = min($limit, $total);

    if ($fetchLimit >= $total) {
        // Just fetch all and shuffle in PHP — cheaper than DB random
        $sql = "
            SELECT mcq_id, chapter_id, question,
                   option_a, option_b, option_c, option_d,
                   correct_answer, explanation, image_url
            FROM mcqs
            WHERE chapter_id IN ($placeholders)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($clean_ids);
        $mcqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        shuffle($mcqs);
        $mcqs = array_slice($mcqs, 0, $fetchLimit);
    } else {
        // Fast random sampling: use a JOIN with a random-offset derived table.
        // We pick $fetchLimit random IDs via PHP random offsets, then fetch those rows.
        // This avoids full-table ORDER BY RAND().
        $randomOffsets = array_unique(array_map(fn() => random_int(0, $total - 1), range(0, $fetchLimit * 3)));
        shuffle($randomOffsets);
        $randomOffsets = array_slice($randomOffsets, 0, $fetchLimit);
        sort($randomOffsets);

        // Use LIMIT/OFFSET per chunk — one optimized query using UNION approach isn't
        // feasible easily, so we do a single query with ORDER BY mcq_id and random id selection.
        // Most performant: get all IDs first, pick random subset in PHP, then fetch by IDs.
        $idSql = "SELECT mcq_id FROM mcqs WHERE chapter_id IN ($placeholders) ORDER BY mcq_id ASC";
        $idStmt = $pdo->prepare($idSql);
        $idStmt->execute($clean_ids);
        $allIds = $idStmt->fetchAll(PDO::FETCH_COLUMN);

        // Pick random IDs in PHP — O(n) but n is just IDs (integers), very fast
        shuffle($allIds);
        $selectedIds = array_slice($allIds, 0, $fetchLimit);

        $idPlaceholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $sql = "
            SELECT mcq_id, chapter_id, question,
                   option_a, option_b, option_c, option_d,
                   correct_answer, explanation, image_url
            FROM mcqs
            WHERE mcq_id IN ($idPlaceholders)
        ";
        $stmt = $pdo->prepare($sql);
        // Bind selected IDs
        foreach ($selectedIds as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $mcqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        shuffle($mcqs); // Shuffle final result for random order
    }

    // Decode HTML entities and normalize
    foreach ($mcqs as &$mcq) {
        $mcq['question']      = html_entity_decode($mcq['question'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $mcq['option_a']      = html_entity_decode($mcq['option_a'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $mcq['option_b']      = html_entity_decode($mcq['option_b'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $mcq['option_c']      = html_entity_decode($mcq['option_c'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $mcq['option_d']      = html_entity_decode($mcq['option_d'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $mcq['explanation']   = html_entity_decode($mcq['explanation'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $mcq['correct_answer'] = strtolower($mcq['correct_answer'] ?? '');
    }
    unset($mcq);

    sendResponse('success', 'Custom test generated successfully', array_values($mcqs), 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
