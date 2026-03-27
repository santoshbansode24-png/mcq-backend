<?php
/**
 * Get Exam History API
 * Veeru
 *
 * Endpoint: GET /api/get_exam_history.php?user_id=123
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    sendResponse('error', 'Invalid user ID', null, 400);
}

try {
    // Make sure table exists
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

    // Fetch history, newest first
    $stmt = $pdo->prepare("SELECT * FROM exam_history WHERE user_id = ? ORDER BY taken_at DESC LIMIT 50");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse('success', 'Exam history fetched', $history, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
