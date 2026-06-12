<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'cors_middleware.php';

if (!isset($_GET['teacher_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing teacher_id']);
    exit();
}

$teacher_id = intval($_GET['teacher_id']);
require_once '../config/db.php';

try {
    // 1. Ensure table exists
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

    // 2. Fetch all exams sent by this teacher
    $examsStmt = $pdo->prepare("
        SELECT cu.id as update_id, cu.class_id, cu.title, cu.message, cu.created_at, c.class_name
        FROM class_updates cu
        LEFT JOIN classes c ON cu.class_id = c.class_id
        WHERE cu.teacher_id = ? AND cu.update_type = 'exam'
        ORDER BY cu.created_at DESC
    ");
    $examsStmt->execute([$teacher_id]);
    $exams = $examsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $exams]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error_message' => $e->getMessage()]);
}
