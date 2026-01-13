<?php
require_once '../config/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting State Board (Marathi) Data Seeding...<br>";

$board = 'STATE_MARATHI';
$subjects_list = [
    'Marathi' => 'Language',
    'English' => 'Language',
    'Mathematics' => 'Core',
    'Science' => 'Core',
    'History' => 'Social Science',
    'Geography' => 'Social Science'
];

try {
    $pdo->beginTransaction();

    for ($i = 1; $i <= 10; $i++) {
        $className = "Class $i";
        
        // 1. Check/Insert Class
        $stmt = $pdo->prepare("SELECT class_id FROM classes WHERE class_name = ? AND board_type = ?");
        $stmt->execute([$className, $board]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$class) {
            $insertClass = $pdo->prepare("INSERT INTO classes (class_name, board_type) VALUES (?, ?)");
            $insertClass->execute([$className, $board]);
            $class_id = $pdo->lastInsertId();
            echo "✅ Created $className ($board)<br>";
        } else {
            $class_id = $class['class_id'];
            // echo "ℹ️ $className already exists (ID: $class_id)<br>";
        }

        // 2. Insert Subjects for this Class
        foreach ($subjects_list as $subName => $subType) {
            // Check if subject exists for this class
            $stmtSub = $pdo->prepare("SELECT subject_id FROM subjects WHERE class_id = ? AND subject_name = ?");
            $stmtSub->execute([$class_id, $subName]);
            $subject = $stmtSub->fetch(PDO::FETCH_ASSOC);

            if (!$subject) {
                // Determine description
                $desc = "$subName for $className ($board)";

                $insertSub = $pdo->prepare("INSERT INTO subjects (class_id, subject_name, description) VALUES (?, ?, ?)");
                $insertSub->execute([$class_id, $subName, $desc]);
                $subject_id = $pdo->lastInsertId();
                echo "&nbsp;&nbsp;&nbsp;&nbsp;➕ Added Subject: $subName<br>";
            } else {
                $subject_id = $subject['subject_id'];
            }

            // 3. Insert Dummy Chapter for this Subject
            $stmtChap = $pdo->prepare("SELECT chapter_id FROM chapters WHERE subject_id = ? AND chapter_name LIKE 'Chapter 1%'");
            $stmtChap->execute([$subject_id]);
            $chapter = $stmtChap->fetch(PDO::FETCH_ASSOC);

            if (!$chapter) {
                $insertChap = $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description, chapter_order) VALUES (?, ?, ?, 1)");
                $insertChap->execute([$subject_id, "Chapter 1: Introduction to $subName", "Basic concepts of $subName"]);
                echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;📄 Added Chapter 1<br>";
            }
        }
    }

    $pdo->commit();
    echo "<br>🎉 Seeding Complete for STATE_MARATHI (Class 1-10)!";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage();
}
?>
