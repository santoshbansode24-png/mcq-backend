<?php
/**
 * Save Exam History API
 * Veeru
 *
 * Endpoint: POST /api/save_exam_history.php
 * Input: { user_id, chapter_ids, subject_names, correct, incorrect, unanswered, total, time_seconds }
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$data = getJsonInput();

$user_id       = isset($data['user_id'])       ? intval($data['user_id'])       : 0;
$chapter_ids   = isset($data['chapter_ids'])   ? trim($data['chapter_ids'])     : '';
$subject_names = isset($data['subject_names']) ? substr(trim($data['subject_names']), 0, 255) : '';
$correct       = isset($data['correct'])       ? intval($data['correct'])       : 0;
$incorrect     = isset($data['incorrect'])     ? intval($data['incorrect'])     : 0;
$unanswered    = isset($data['unanswered'])    ? intval($data['unanswered'])    : 0;
$total         = isset($data['total'])         ? intval($data['total'])         : 0;
$time_seconds  = isset($data['time_seconds'])  ? intval($data['time_seconds'])  : 0;

if ($user_id <= 0 || $total <= 0) {
    sendResponse('error', 'Missing required fields', null, 400);
}

$percentage = $total > 0 ? round(($correct / $total) * 100, 1) : 0;

try {
    // Ensure the table exists (auto-create on first run)
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_history (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        chapter_ids VARCHAR(500) DEFAULT '',
        subject_names VARCHAR(255) DEFAULT '',
        correct     INT NOT NULL DEFAULT 0,
        incorrect   INT NOT NULL DEFAULT 0,
        unanswered  INT NOT NULL DEFAULT 0,
        total       INT NOT NULL DEFAULT 0,
        percentage  DECIMAL(5,1) NOT NULL DEFAULT 0,
        time_seconds INT NOT NULL DEFAULT 0,
        taken_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_eh_user_id (user_id),
        INDEX idx_eh_taken_at (taken_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("
        INSERT INTO exam_history
            (user_id, chapter_ids, subject_names, correct, incorrect, unanswered, total, percentage, time_seconds)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $chapter_ids, $subject_names, $correct, $incorrect, $unanswered, $total, $percentage, $time_seconds]);
    $historyId = $pdo->lastInsertId();

    // If answers array was provided, record wrong questions into negative_basket
    if (!empty($data['answers']) && is_array($data['answers'])) {
        try {
            $stmtBasket = $pdo->prepare("
                INSERT INTO negative_basket 
                (student_id, question_id, subject_name, chapter_name, chapter_id, selected_answer, correct_answer, wrong_attempt_count, last_wrong_date, resolved)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 0)
                ON DUPLICATE KEY UPDATE 
                    wrong_attempt_count = wrong_attempt_count + 1,
                    selected_answer = VALUES(selected_answer),
                    correct_answer = VALUES(correct_answer),
                    last_wrong_date = NOW(),
                    resolved = 0
            ");

            foreach ($data['answers'] as $ans) {
                $qId = intval($ans['mcq_id'] ?? 0);
                $sel = strtolower(trim($ans['selected_option'] ?? ''));
                $cor = strtolower(trim($ans['correct_answer'] ?? ''));
                $corLetter = str_replace('option_', '', $cor);

                if ($qId > 0 && !empty($sel) && $sel !== 'skip' && $sel !== 'none' && $sel !== $corLetter && $sel !== ('option_' . $corLetter)) {
                    $subN = !empty($ans['subject_name']) ? $ans['subject_name'] : ($subject_names ?: 'Exam');
                    $chN = !empty($ans['chapter_name']) ? $ans['chapter_name'] : 'Exam';
                    $chId = intval($ans['chapter_id'] ?? 0);

                    $stmtBasket->execute([$user_id, $qId, $subN, $chN, $chId, $sel, $corLetter]);
                }
            }
        } catch (Exception $e) {}
    }

    sendResponse('success', 'Exam history saved', ['id' => $historyId], 201);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
