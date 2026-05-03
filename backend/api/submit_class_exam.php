<?php
/**
 * Submit Class Exam Results
 * Receives exam scores from a student for a specific teacher's syllabus exam
 */
require_once 'cors_middleware.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['update_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: user_id or update_id']);
    exit();
}

$user_id = intval($input['user_id']);
$update_id = intval($input['update_id']);
$correct = intval($input['correct'] ?? 0);
$incorrect = intval($input['incorrect'] ?? 0);
$unanswered = intval($input['unanswered'] ?? 0);
$total = intval($input['total'] ?? 0);
$time_seconds = intval($input['time_seconds'] ?? 0);

require_once '../config/db.php';

try {
    // 1. Ensure the table exists (Automated migration)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS class_exam_results (
            result_id INT AUTO_INCREMENT PRIMARY KEY,
            update_id INT NOT NULL,
            user_id INT NOT NULL,
            correct INT DEFAULT 0,
            incorrect INT DEFAULT 0,
            unanswered INT DEFAULT 0,
            total INT DEFAULT 0,
            time_seconds INT DEFAULT 0,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_attempt (update_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Insert or Update the exam result (allow student to retake and overwrite, or just keep highest/latest)
    // For simplicity, we overwrite with the latest attempt.
    $stmt = $pdo->prepare("
        INSERT INTO class_exam_results (update_id, user_id, correct, incorrect, unanswered, total, time_seconds, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            correct = VALUES(correct),
            incorrect = VALUES(incorrect),
            unanswered = VALUES(unanswered),
            total = VALUES(total),
            time_seconds = VALUES(time_seconds),
            submitted_at = NOW()
    ");
    
    $stmt->execute([$update_id, $user_id, $correct, $incorrect, $unanswered, $total, $time_seconds]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Class exam results saved successfully'
    ]);

} catch (PDOException $e) {
    error_log("[Veeru] Class Exam Submit Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
}
?>
