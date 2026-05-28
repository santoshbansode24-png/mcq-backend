<?php
require_once 'config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS live_exams (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT(11),
        class_id INT(11),
        chapter_id INT(11),
        title VARCHAR(255),
        duration_minutes INT(11),
        selected_question_ids LONGTEXT,
        status ENUM('active','completed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Successfully created live_exams table on Railway!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
