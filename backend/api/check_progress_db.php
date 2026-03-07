<?php
/**
 * Check Progress Tables on Live Server
 * Point your browser to: https://api.veeruapp.in/backend/api/check_progress_db.php
 */
require_once '../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== PROGRESS DB HEALTH CHECK ===\n\n";

$tables = ['content_progress', 'mcq_attempts', 'student_progress', 'users', 'flashcard_progress'];

foreach($tables as $table){
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "✅ Table '$table' EXISTS. Rows: $count\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S02') {
            echo "❌ Table '$table' DOES NOT EXIST!\n";
        } else {
            echo "❓ Table '$table' Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nDone.";
?>
