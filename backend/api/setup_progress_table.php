<?php
require_once '../config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS content_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chapter_id INT NOT NULL,
        set_index INT NOT NULL,
        content_type ENUM('flashcard', 'mcq', 'video', 'note') NOT NULL,
        status VARCHAR(20) DEFAULT 'completed',
        score INT DEFAULT 0,
        total INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_progress (user_id, chapter_id, set_index, content_type)
    )";

    $pdo->exec($sql);
    echo "Table content_progress created successfully.";

    // Migration: Copy existing flashcard_progress data to content_progress
    // Check if flashcard_progress exists
    $check = $pdo->query("SHOW TABLES LIKE 'flashcard_progress'");
    if($check->rowCount() > 0) {
        $sqlMigrate = "INSERT IGNORE INTO content_progress (user_id, chapter_id, set_index, content_type, status)
                       SELECT user_id, chapter_id, set_index, 'flashcard', 'completed' FROM flashcard_progress";
        $pdo->exec($sqlMigrate);
        echo "\nMigrated existing flashcard data.";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
