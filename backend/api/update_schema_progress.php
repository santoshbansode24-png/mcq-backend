<?php
/**
 * Schema Updater for Progress Tracking (Simplified)
 * Veeru
 */

require_once '../config/db.php';

echo "<h2>Progress Tracking Schema Update</h2>";

try {
    // defined in db.php
    /** @var PDO $pdo */

    // 1. Create flashcard_progress table (No Foreign Keys to avoid mismatch issues)
    $sql = "CREATE TABLE IF NOT EXISTS flashcard_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chapter_id INT NOT NULL,
        set_index INT NOT NULL,
        completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attempt (user_id, chapter_id, set_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "<p style='color: green'>✅ Table <strong>flashcard_progress</strong> created/checked (Simplified).</p>";

} catch (PDOException $e) {
    echo "<p style='color: red'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<p>Done.</p>";
?>
