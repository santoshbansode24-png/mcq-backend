<?php
/**
 * Get Mega Revision MCQs API
 *
 * Fetches MCQs for the Mega Revision Blitz.
 *
 * When chapter_ids is provided (comma-separated or JSON array) → use ONLY those chapters.
 * Otherwise fall back to ALL chapters in the user's study plan.
 *
 * Selection logic:
 *   - >= 50 available  → pick 50 random
 *   - <  50 available  → round down to nearest 10 (e.g. 48 → 40)
 *   - <  10 available  → take all
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$medium  = isset($_GET['medium'])  ? strtolower(trim($_GET['medium'])) : 'english';
$lang    = strtolower(trim($_GET['lang'] ?? $_GET['language'] ?? ($medium === 'marathi' ? 'mr' : 'en')));

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit();
}

// ---------- Resolve which chapter IDs to query ----------
$chapter_ids = [];

if (!empty($_GET['chapter_ids'])) {
    $raw = $_GET['chapter_ids'];

    // Could be JSON array string or comma-separated integers
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $chapter_ids = array_map('intval', $decoded);
    } else {
        $chapter_ids = array_map('intval', explode(',', $raw));
    }
    $chapter_ids = array_filter($chapter_ids); // remove zeros
}

try {
    if (empty($chapter_ids)) {
        // Fallback: use ALL chapters from this user's study plan
        $stmt = $pdo->prepare(
            "SELECT DISTINCT chapter_id FROM study_tasks WHERE user_id = ? AND chapter_id IS NOT NULL"
        );
        $stmt->execute([$user_id]);
        $chapter_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (empty($chapter_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No chapters found for this blitz.']);
        exit();
    }

    $ch_placeholders = implode(',', array_fill(0, count($chapter_ids), '?'));

    $sql = "SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d,
                   question_hi, option_a_hi, option_b_hi, option_c_hi, option_d_hi, explanation_hi,
                   question_mr, option_a_mr, option_b_mr, option_c_mr, option_d_mr, explanation_mr,
                   correct_answer, explanation, difficulty, medium, image_url
            FROM mcqs
            WHERE chapter_id IN ($ch_placeholders) AND medium = ?";

    $params = array_merge(array_values($chapter_ids), [$medium]);
    $stmt   = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_mcqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_available = count($all_mcqs);

    if ($total_available === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No MCQs available for the selected chapters yet.']);
        exit();
    }

    // Apply selection logic
    $limit = 50;
    if ($total_available < 50) {
        $limit = floor($total_available / 10) * 10;
        if ($limit < 10) $limit = $total_available;
    }

    shuffle($all_mcqs);
    $selected_mcqs = array_slice($all_mcqs, 0, $limit);

    foreach ($selected_mcqs as &$row) {
        $isTranslated = true;
        if ($lang === 'mr') {
            $isTranslated = !empty($row['question_mr']);
            if (!empty($row['question_mr'])) $row['question'] = $row['question_mr'];
            if (!empty($row['option_a_mr'])) $row['option_a'] = $row['option_a_mr'];
            if (!empty($row['option_b_mr'])) $row['option_b'] = $row['option_b_mr'];
            if (!empty($row['option_c_mr'])) $row['option_c'] = $row['option_c_mr'];
            if (!empty($row['option_d_mr'])) $row['option_d'] = $row['option_d_mr'];
            if (!empty($row['explanation_mr'])) $row['explanation'] = $row['explanation_mr'];
        } elseif ($lang === 'hi') {
            $isTranslated = !empty($row['question_hi']);
            if (!empty($row['question_hi'])) $row['question'] = $row['question_hi'];
            if (!empty($row['option_a_hi'])) $row['option_a'] = $row['option_a_hi'];
            if (!empty($row['option_b_hi'])) $row['option_b'] = $row['option_b_hi'];
            if (!empty($row['option_c_hi'])) $row['option_c'] = $row['option_c_hi'];
            if (!empty($row['option_d_hi'])) $row['option_d'] = $row['option_d_hi'];
            if (!empty($row['explanation_hi'])) $row['explanation'] = $row['explanation_hi'];
        }
        $row['is_translated'] = $isTranslated;
    }
    unset($row);

    echo json_encode([
        'status'          => 'success',
        'count'           => count($selected_mcqs),
        'total_available' => $total_available,
        'chapter_ids'     => $chapter_ids,
        'data'            => $selected_mcqs
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
