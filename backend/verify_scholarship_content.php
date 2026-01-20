<?php
header('Content-Type: application/json');
require_once 'config/db.php';

try {
    // 1. Get Subjects for Class 37
    $stmt = $pdo->prepare("SELECT subject_id, subject_name FROM subjects WHERE class_id = 37");
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($subjects as $subject) {
        // 2. Get Chapters for each subject
        $stmtCh = $pdo->prepare("SELECT chapter_id, chapter_name FROM chapters WHERE subject_id = ?");
        $stmtCh->execute([$subject['subject_id']]);
        $chapters = $stmtCh->fetchAll(PDO::FETCH_ASSOC);

        $subjectData = [
            'subject_name' => $subject['subject_name'],
            'chapters_count' => count($chapters),
            'chapters' => []
        ];

        foreach ($chapters as $chapter) {
            // 3. Get MCQ count for each chapter
            $stmtMcq = $pdo->prepare("SELECT COUNT(*) as count FROM mcqs WHERE chapter_id = ?");
            $stmtMcq->execute([$chapter['chapter_id']]);
            $mcqCount = $stmtMcq->fetch(PDO::FETCH_ASSOC)['count'];

            $subjectData['chapters'][] = [
                'chapter_id' => $chapter['chapter_id'],
                'chapter_name' => $chapter['chapter_name'],
                'mcq_count' => $mcqCount
            ];
        }

        $data[] = $subjectData;
    }

    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
