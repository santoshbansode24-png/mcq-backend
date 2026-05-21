<?php
require_once '../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== FIXING MISSING FOREIGN KEYS ===\n";

$fk_statements = [
    // vocab_bookmarks -> vocab_words(word_id)
    "ALTER TABLE `vocab_bookmarks` ADD CONSTRAINT `fk_vb_word` FOREIGN KEY (`word_id`) REFERENCES `vocab_words`(`word_id`) ON DELETE CASCADE;",
    
    // mcq_progress -> subjects(subject_id), chapters(chapter_id), mcqs(mcq_id)
    "ALTER TABLE `mcq_progress` ADD CONSTRAINT `fk_mp_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE;",
    "ALTER TABLE `mcq_progress` ADD CONSTRAINT `fk_mp_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`chapter_id`) ON DELETE CASCADE;",
    "ALTER TABLE `mcq_progress` ADD CONSTRAINT `fk_mp_question` FOREIGN KEY (`question_id`) REFERENCES `mcqs`(`mcq_id`) ON DELETE CASCADE;",
    
    // chapter_completion -> chapters(chapter_id)
    "ALTER TABLE `chapter_completion` ADD CONSTRAINT `fk_cc_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`chapter_id`) ON DELETE CASCADE;"
];

foreach ($fk_statements as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ Added: $sql\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42000' && strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "⚠️ Skipped (already exists): $sql\n";
        } else {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nRemaining Foreign Keys setup completed!\n";
?>
