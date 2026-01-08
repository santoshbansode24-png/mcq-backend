<?php
require_once '../config/db.php';

try {
    echo "=== LATEST 5 MCQS ===\n";
    $stmt = $pdo->query("SELECT mcq_id, question, chapter_id, created_at FROM mcqs ORDER BY mcq_id DESC LIMIT 5");
    $mcqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($mcqs)) echo "No MCQs found.\n";
    foreach ($mcqs as $m) {
        echo "[ID: {$m['mcq_id']}] Ch:{$m['chapter_id']} - " . substr($m['question'], 0, 40) . "... (Timestamp: " . ($m['created_at'] ?? 'N/A') . ")\n";
    }

    echo "\n=== LATEST 5 FLASHCARDS ===\n";
    $stmt = $pdo->query("SELECT id, question_front, chapter_id, created_at FROM flashcards ORDER BY id DESC LIMIT 5");
    $fcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($fcs)) echo "No Flashcards found.\n";
    foreach ($fcs as $f) {
        echo "[ID: {$f['id']}] Ch:{$f['chapter_id']} - " . substr($f['question_front'], 0, 40) . "... (Timestamp: " . ($f['created_at'] ?? 'N/A') . ")\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
