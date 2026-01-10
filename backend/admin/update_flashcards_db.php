<?php
require_once '../config/db.php';

try {
    // Add chapter_id column if it doesn't exist
    // We use a safe approach: trying to add it, if it fails (exists), we catch it or ignore.
    // Better: Check if column exists.
    
    $check = $pdo->query("SHOW COLUMNS FROM flashcards LIKE 'chapter_id'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE flashcards ADD COLUMN chapter_id INT NOT NULL AFTER id");
        $pdo->exec("ALTER TABLE flashcards ADD INDEX (chapter_id)");
        echo "Added chapter_id column to flashcards table.<br>";
    } else {
        echo "chapter_id column already exists.<br>";
    }

    echo "Database update complete.";
} catch (PDOException $e) {
    echo "Error updating table: " . $e->getMessage();
}
?>
