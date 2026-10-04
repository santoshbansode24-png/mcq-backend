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
    $colsStmt = $pdo->query("SHOW COLUMNS FROM negative_basket");
    $res['columns'] = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $res['cols_error'] = $e->getMessage();
}

echo json_encode($res, JSON_PRETTY_PRINT);
exit();
?>
