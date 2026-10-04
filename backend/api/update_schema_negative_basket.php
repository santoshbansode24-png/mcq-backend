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
        INSERT INTO negative_basket 
        (student_id, question_id, subject_name, chapter_name, chapter_id, selected_answer, correct_answer, wrong_attempt_count, last_wrong_date, resolved)
        SELECT 
            t.student_id,
            t.question_id,
            t.subject_name,
            t.chapter_name,
            t.chapter_id,
            t.selected_answer,
            t.correct_answer,
            t.wrong_count,
            t.last_wrong,
            0
        FROM (
            SELECT 
                ma.user_id as student_id,
                ma.mcq_id as question_id,
                COALESCE(MAX(s.subject_name), 'General') as subject_name,
                COALESCE(MAX(ch.chapter_name), 'Practice') as chapter_name,
                MAX(ma.chapter_id) as chapter_id,
                MAX(ma.selected_answer) as selected_answer,
                MAX(ma.correct_answer) as correct_answer,
                COUNT(*) as wrong_count,
                MAX(ma.attempted_at) as last_wrong
            FROM mcq_attempts ma
            LEFT JOIN chapters ch ON ma.chapter_id = ch.chapter_id
            LEFT JOIN subjects s ON ch.subject_id = s.subject_id
            WHERE ma.is_correct = 0
            GROUP BY ma.user_id, ma.mcq_id
        ) t
        ON DUPLICATE KEY UPDATE 
            wrong_attempt_count = VALUES(wrong_attempt_count),
            last_wrong_date = VALUES(last_wrong_date)
    ");
    $res['backfill_count'] = $backfillStmt;

    // Exam attempts backfill
    $examBackfill = $pdo->exec("
        INSERT INTO negative_basket 
        (student_id, question_id, subject_name, chapter_name, chapter_id, selected_answer, correct_answer, wrong_attempt_count, last_wrong_date, resolved)
        SELECT 
            t.student_id,
            t.question_id,
            t.subject_name,
            t.chapter_name,
            t.chapter_id,
            t.selected_answer,
            t.correct_answer,
            t.wrong_count,
            t.last_wrong,
            0
        FROM (
            SELECT 
                ea.user_id as student_id,
                ea.mcq_id as question_id,
                COALESCE(MAX(s.subject_name), 'General') as subject_name,
                COALESCE(MAX(ch.chapter_name), 'Exam') as chapter_name,
                MAX(ea.chapter_id) as chapter_id,
                MAX(ea.selected_option) as selected_answer,
                MAX(ea.correct_option) as correct_answer,
                COUNT(*) as wrong_count,
                MAX(ea.created_at) as last_wrong
            FROM exam_attempt_answers ea
            LEFT JOIN chapters ch ON ea.chapter_id = ch.chapter_id
            LEFT JOIN subjects s ON ch.subject_id = s.subject_id
            WHERE ea.is_correct = 0
            GROUP BY ea.user_id, ea.mcq_id
        ) t
        ON DUPLICATE KEY UPDATE 
            wrong_attempt_count = VALUES(wrong_attempt_count),
            last_wrong_date = VALUES(last_wrong_date)
    ");
    $res['exam_backfill_count'] = $examBackfill;
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
