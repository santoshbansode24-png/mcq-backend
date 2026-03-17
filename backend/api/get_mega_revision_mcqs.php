<?php
/**
 * Get Mega Revision MCQs API
 * Fetches up to 50 random MCQs from all chapters in the user's study plan
 * Logic: 
 * - If >= 50 available, pick 50 random.
 * - If < 50 available, round down to nearest 10 (e.g., 48 -> 40).
 * - If < 10 available, pick all.
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$medium = isset($_GET['medium']) ? strtolower(trim($_GET['medium'])) : 'english';

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit();
}

try {
    // 1. Get all chapters that have been assigned to this user in their current plan
    $stmt = $pdo->prepare("SELECT DISTINCT chapter_id FROM study_tasks WHERE user_id = ? AND chapter_id IS NOT NULL");
    $stmt->execute([$user_id]);
    $chapter_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($chapter_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No chapters found in your study roadmap.']);
        exit();
    }

    $ch_placeholders = implode(',', array_fill(0, count($chapter_ids), '?'));

    // 2. Fetch all MCQs for these chapters
    $sql = "SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, medium, image_url 
            FROM mcqs 
            WHERE chapter_id IN ($ch_placeholders) AND medium = ?";
    
    $params = array_merge($chapter_ids, [$medium]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_mcqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_available = count($all_mcqs);
    
    if ($total_available == 0) {
        echo json_encode(['status' => 'error', 'message' => 'No MCQs available for your selected chapters yet.']);
        exit();
    }

    // 3. Apply Selection Logic
    $limit = 50;
    if ($total_available < 50) {
        $limit = floor($total_available / 10) * 10;
        if ($limit < 10) $limit = $total_available; // If less than 10, take what we have
    }

    // Shuffle and slice
    shuffle($all_mcqs);
    $selected_mcqs = array_slice($all_mcqs, 0, $limit);

    echo json_encode([
        'status' => 'success',
        'count' => count($selected_mcqs),
        'total_available' => $total_available,
        'data' => $selected_mcqs
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
