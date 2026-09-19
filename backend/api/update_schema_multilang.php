<?php
/**
 * Database Schema Update - Multi-Language Translation (Hindi & Marathi)
 * Veeru
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $queries = [
        // 1. MCQs Table Columns
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS question_hi TEXT DEFAULT NULL AFTER question",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS question_mr TEXT DEFAULT NULL AFTER question_hi",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS option_a_hi TEXT DEFAULT NULL AFTER option_a",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS option_a_mr TEXT DEFAULT NULL AFTER option_a_hi",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS option_b_hi TEXT DEFAULT NULL AFTER option_b",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS option_b_mr TEXT DEFAULT NULL AFTER option_b_hi",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS option_c_hi TEXT DEFAULT NULL AFTER option_c",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS option_c_mr TEXT DEFAULT NULL AFTER option_c_hi",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS option_d_hi TEXT DEFAULT NULL AFTER option_d",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS option_d_mr TEXT DEFAULT NULL AFTER option_d_hi",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS explanation_hi TEXT DEFAULT NULL AFTER explanation",
        "ALTER TABLE mcqs ADD COLUMN IF NOT EXISTS explanation_mr TEXT DEFAULT NULL AFTER explanation_hi",

        // 2. Flashcards Table Columns
        "ALTER TABLE flashcards ADD COLUMN IF NOT EXISTS question_front_hi TEXT DEFAULT NULL AFTER question_front",
        "ALTER TABLE flashcards ADD COLUMN IF NOT EXISTS question_front_mr TEXT DEFAULT NULL AFTER question_front_hi",
        "ALTER TABLE flashcards ADD COLUMN IF NOT EXISTS answer_back_hi TEXT DEFAULT NULL AFTER answer_back",
        "ALTER TABLE flashcards ADD COLUMN IF NOT EXISTS answer_back_mr TEXT DEFAULT NULL AFTER answer_back_hi",

        // 3. Quick Revision Table Columns
        "ALTER TABLE quick_revision ADD COLUMN IF NOT EXISTS title_hi VARCHAR(255) DEFAULT NULL AFTER title",
        "ALTER TABLE quick_revision ADD COLUMN IF NOT EXISTS title_mr VARCHAR(255) DEFAULT NULL AFTER title_hi",
        "ALTER TABLE quick_revision ADD COLUMN IF NOT EXISTS key_points_hi JSON DEFAULT NULL AFTER key_points",
        "ALTER TABLE quick_revision ADD COLUMN IF NOT EXISTS key_points_mr JSON DEFAULT NULL AFTER key_points_hi",
        "ALTER TABLE quick_revision ADD COLUMN IF NOT EXISTS summary_hi TEXT DEFAULT NULL AFTER summary",
        "ALTER TABLE quick_revision ADD COLUMN IF NOT EXISTS summary_mr TEXT DEFAULT NULL AFTER summary_hi"
    ];

    $executed = 0;
    foreach ($queries as $sql) {
        try {
            $pdo->exec($sql);
            $executed++;
        } catch (PDOException $e) {
            // Ignore column already exists errors
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Multi-Language Database Schema updated successfully!',
        'queries_executed' => $executed
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
