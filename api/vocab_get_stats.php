<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once '../config/db.php';

try {
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId) throw new Exception('Valid user_id required');

    // Ensure stats exist
    $pdo->prepare("INSERT IGNORE INTO user_vocab_stats (user_id) VALUES (?)")->execute([$userId]);

    // Query matching your EXACT database structure
    // Query matching your EXACT database structure
    $sql = "SELECT 
                uvs.current_set,
                uvs.sets_completed,
                uvs.words_mastered as mastered_words, 
                uvs.experience_points,
                uvs.current_streak as streak_days,
                (SELECT COUNT(*) FROM user_vocab_progress WHERE user_id = :uid1 AND mastery_status = 'New') as new_words,
                (SELECT COUNT(*) FROM user_vocab_progress WHERE user_id = :uid2 AND mastery_status = 'Learning') as learning_words,
                (SELECT COUNT(*) FROM user_vocab_progress WHERE user_id = :uid3 AND mastery_status = 'Review') as review_words,
                (SELECT COUNT(*) FROM user_vocab_progress WHERE user_id = :uid4 AND next_review_date <= CURDATE()) as due_today
            FROM user_vocab_stats uvs
            WHERE uvs.user_id = :uid5";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid1'=>$userId, ':uid2'=>$userId, ':uid3'=>$userId, ':uid4'=>$userId, ':uid5'=>$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stats) $stats = [];

    ob_clean();
    echo json_encode([
        'status' => 'success',
        'data' => $stats
    ], JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>