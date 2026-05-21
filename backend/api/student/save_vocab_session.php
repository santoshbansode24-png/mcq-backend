<?php
/**
 * Save Vocabulary Session API
 * Endpoint: POST /api/student/save_vocab_session.php
 */
require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();

$required = ['user_id', 'words_learned_count', 'quiz_score'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$user_id = (int)$input['user_id'];
$words_learned_count = (int)$input['words_learned_count'];
$quiz_score = (int)$input['quiz_score'];
$bookmarked_word_ids = isset($input['bookmarked_word_ids']) && is_array($input['bookmarked_word_ids']) ? $input['bookmarked_word_ids'] : [];

try {
    $pdo->beginTransaction();

    // 1. Update Vocab Progress (Streak & Overall Words Learned)
    $stmt = $pdo->prepare("
        INSERT INTO vocab_progress (user_id, words_learned, last_quiz_score, current_streak, last_active_date)
        VALUES (?, ?, ?, 1, CURDATE())
        ON DUPLICATE KEY UPDATE 
            words_learned = words_learned + VALUES(words_learned),
            last_quiz_score = VALUES(last_quiz_score),
            current_streak = CASE 
                WHEN last_active_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN current_streak + 1
                WHEN last_active_date = CURDATE() THEN current_streak
                ELSE 1 
            END,
            last_active_date = CURDATE()
    ");
    $stmt->execute([$user_id, $words_learned_count, $quiz_score]);

    // 2. Save Bookmarks
    if (!empty($bookmarked_word_ids)) {
        $bookmark_stmt = $pdo->prepare("INSERT IGNORE INTO vocab_bookmarks (user_id, word_id) VALUES (?, ?)");
        foreach ($bookmarked_word_ids as $word_id) {
            $word_id = (int)$word_id;
            if ($word_id > 0) {
                $bookmark_stmt->execute([$user_id, $word_id]);
            }
        }
    }

    $pdo->commit();

    sendResponse('success', 'Vocabulary session saved successfully!', [
        'words_processed' => $words_learned_count,
        'bookmarks_saved' => count($bookmarked_word_ids)
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == 23000) {
        sendResponse('error', 'Database constraint violation. Ensure user and bookmarked words exist.', null, 400);
    } else {
        sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
    }
}
?>
