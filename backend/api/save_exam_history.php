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

    sendResponse('success', 'Exam history saved', ['id' => $pdo->lastInsertId()], 201);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
