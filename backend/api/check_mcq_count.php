<?php
require_once '../config/db.php';
try {
    // Count MCQs for Chapter 15
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mcqs WHERE chapter_id = ?");
    $stmt->execute([15]);
    $count15 = $stmt->fetchColumn();
    echo "Chapter 15 MCQs: $count15\n";

    // Check if they landed in another chapter by mistake (listing recent chapters with high MCQ counts)
    $stmt = $pdo->query("SELECT chapter_id, COUNT(*) as c FROM mcqs GROUP BY chapter_id HAVING c > 0 ORDER BY chapter_id DESC LIMIT 10");
    echo "Recent Chapters with MCQs:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Chapter {$row['chapter_id']}: {$row['c']} MCQs\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
