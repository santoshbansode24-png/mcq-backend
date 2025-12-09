<?php
// backend/fix_duplicates.php
require_once 'config/db.php';

echo "<h1>Fixing Duplicates for Class 10</h1>";

try {
    // 1. Delete ALL subjects for Class 10 (to clear duplicates)
    $stmt = $pdo->prepare("DELETE FROM subjects WHERE class_id = 10");
    $stmt->execute();
    echo "✅ Deleted " . $stmt->rowCount() . " duplicate/old subjects.<br>";

    // 2. Re-insert CLEAN subjects
    $subjects = ['Mathematics', 'Science', 'English', 'Social Studies'];
    foreach ($subjects as $sub) {
        $stmt = $pdo->prepare("INSERT INTO subjects (class_id, subject_name, description) VALUES (10, ?, ?)");
        $stmt->execute([$sub, "$sub for Class 10"]);
    }
    echo "✅ Re-inserted 4 clean subjects.<br>";

    // 3. Re-insert Dummy Chapter (since we deleted subjects, chapters cascaded)
    $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_name = 'Mathematics' AND class_id = 10");
    $stmt->execute();
    $mathId = $stmt->fetchColumn();

    if ($mathId) {
        $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description) VALUES (?, 'Real Numbers', 'Introduction to Real Numbers')")->execute([$mathId]);
        echo "✅ Re-inserted Chapter 'Real Numbers'.<br>";
    }

    echo "<h3>✅ Database is Clean & Ready!</h3>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
