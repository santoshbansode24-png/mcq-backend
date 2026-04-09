<?php
/**
 * Update Study Tasks Schema
 * Adds new task types to the ENUM
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // Add chapter_ids column if missing
    try {
        $pdo->exec("ALTER TABLE study_tasks ADD COLUMN chapter_ids TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignore if error is duplicate column
    }

    // Update any invalid task types to a safe default to prevent truncation
    $pdo->exec("UPDATE study_tasks SET task_type = 'custom' WHERE task_type NOT IN ('revision', 'quiz', 'video', 'custom', 'practice', 'notes', 'flashcard', 'mega')");
    
    // Modify the task_type ENUM to support notes, flashcards, and mega
    $sql = "ALTER TABLE study_tasks MODIFY COLUMN task_type ENUM('revision', 'quiz', 'video', 'custom', 'practice', 'notes', 'flashcard', 'mega') NOT NULL";
    $pdo->exec($sql);

    echo json_encode([
        'status' => 'success',
        'message' => 'study_tasks table updated with new task types.'
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Schema Update Failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
