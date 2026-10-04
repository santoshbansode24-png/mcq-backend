<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$res = [];
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `negative_basket` (
            `basket_id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `question_id` INT NOT NULL,
            `subject_name` VARCHAR(150) DEFAULT NULL,
            `chapter_name` VARCHAR(150) DEFAULT NULL,
            `chapter_id` INT DEFAULT NULL,
            `selected_answer` VARCHAR(100) DEFAULT NULL,
            `correct_answer` VARCHAR(100) DEFAULT NULL,
            `wrong_attempt_count` INT NOT NULL DEFAULT 1,
            `last_wrong_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `resolved` TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY `unique_student_question` (`student_id`, `question_id`),
            INDEX `idx_student` (`student_id`),
            INDEX `idx_resolved` (`resolved`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $res['negative_basket_table'] = 'SUCCESS';
} catch (PDOException $e) {
    $res['negative_basket_table'] = $e->getMessage();
}

try {
    $backfillStmt = $pdo->exec("
        INSERT IGNORE INTO negative_basket 
        (student_id, question_id, subject_name, chapter_name, chapter_id, selected_answer, correct_answer, wrong_attempt_count, last_wrong_date, resolved)
        SELECT 
            ma.user_id as student_id,
            ma.mcq_id as question_id,
            COALESCE(s.subject_name, 'General') as subject_name,
            COALESCE(ch.chapter_name, 'Practice') as chapter_name,
            ma.chapter_id,
            ma.selected_answer,
            ma.correct_answer,
            COUNT(*) as wrong_count,
            MAX(ma.attempted_at) as last_wrong,
            0 as resolved
        FROM mcq_attempts ma
        LEFT JOIN chapters ch ON ma.chapter_id = ch.chapter_id
        LEFT JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE ma.is_correct = 0
        GROUP BY ma.user_id, ma.mcq_id
    ");
    $res['backfill_count'] = $backfillStmt;
} catch (Exception $e) {
    $res['backfill_error'] = $e->getMessage();
}

try {
    $res['total_in_negative_basket'] = $pdo->query("SELECT COUNT(*) FROM negative_basket")->fetchColumn();
    $res['student_8_in_basket'] = $pdo->query("SELECT COUNT(*) FROM negative_basket WHERE student_id = 8")->fetchColumn();
    $res['student_8_rows'] = $pdo->query("SELECT nb.*, m.question FROM negative_basket nb LEFT JOIN mcqs m ON nb.question_id = m.mcq_id WHERE nb.student_id = 8 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $res['diag_error'] = $e->getMessage();
}

echo json_encode($res, JSON_PRETTY_PRINT);
exit();
?>
