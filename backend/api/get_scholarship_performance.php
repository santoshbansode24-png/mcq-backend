<?php
/**
 * Get Scholarship Performance & Syllabus Tracker API
 * Returns Readiness %, Chapter-wise Syllabus Tracker, Weak Chapters, and Negative Basket
 * Veeru API
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=UTF-8');

// Inject CORS Headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 38;

if ($user_id <= 0) {
    sendResponse('error', 'Invalid user_id', null, 400);
}

try {
    // 1. Fetch Subjects for Scholarship Class (or fallback to standard scholarship subjects)
    $stmtSub = $pdo->prepare("SELECT subject_id, subject_name FROM subjects WHERE class_id = ? ORDER BY subject_id ASC");
    $stmtSub->execute([$class_id]);
    $subjects = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subjects)) {
        // Fallback: look for class 37 or any class with scholarship in name
        $stmtSubFallback = $pdo->query("
            SELECT s.subject_id, s.subject_name 
            FROM subjects s 
            JOIN classes c ON s.class_id = c.class_id 
            WHERE c.class_name LIKE '%Scholarship%' OR c.class_id IN (37, 38)
            LIMIT 6
        ");
        $subjects = $stmtSubFallback->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Fetch student's progress by chapter
    $stmtProg = $pdo->prepare("
        SELECT chapter_id, AVG(percentage) as avg_score, COUNT(*) as attempts_count, MAX(completed_at) as last_attempt
        FROM student_progress
        WHERE user_id = ?
        GROUP BY chapter_id
    ");
    $stmtProg->execute([$user_id]);
    $progressByChapter = [];
    while ($r = $stmtProg->fetch(PDO::FETCH_ASSOC)) {
        $progressByChapter[$r['chapter_id']] = $r;
    }

    // Also check exam_attempt_answers for chapter-level accuracy
    $stmtAnswers = $pdo->prepare("
        SELECT chapter_id, 
               SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_q,
               COUNT(*) as total_q
        FROM exam_attempt_answers
        WHERE user_id = ? AND chapter_id IS NOT NULL AND chapter_id > 0
        GROUP BY chapter_id
    ");
    $stmtAnswers->execute([$user_id]);
    $chapterAnswerStats = [];
    while ($ans = $stmtAnswers->fetch(PDO::FETCH_ASSOC)) {
        $chapterAnswerStats[$ans['chapter_id']] = $ans;
    }

    $totalChaptersAcross = 0;
    $completedChaptersAcross = 0;
    $inProgressChaptersAcross = 0;
    $pendingChaptersAcross = 0;

    $subjectResults = [];
    $weakChapters = [];

    foreach ($subjects as $sub) {
        $subId = $sub['subject_id'];
        
        // Fetch chapters for this subject
        $stmtCh = $pdo->prepare("
            SELECT ch.chapter_id, ch.chapter_name,
                   (SELECT COUNT(*) FROM mcqs m WHERE m.chapter_id = ch.chapter_id) as total_mcqs
            FROM chapters ch
            WHERE ch.subject_id = ?
            ORDER BY ch.chapter_id ASC
        ");
        $stmtCh->execute([$subId]);
        $chapters = $stmtCh->fetchAll(PDO::FETCH_ASSOC);

        $subTotal = count($chapters);
        $subCompleted = 0;
        $subChaptersList = [];

        foreach ($chapters as $ch) {
            $chId = $ch['chapter_id'];
            $prog = $progressByChapter[$chId] ?? null;
            $ansStat = $chapterAnswerStats[$chId] ?? null;

            $status = 'pending'; // 'completed' | 'in_progress' | 'pending'
            $accuracy = 0;
            $testsTaken = 0;

            if ($prog) {
                $testsTaken = intval($prog['attempts_count']);
                $accuracy = round(floatval($prog['avg_score']), 1);
            } elseif ($ansStat && $ansStat['total_q'] > 0) {
                $accuracy = round(($ansStat['correct_q'] / $ansStat['total_q']) * 100, 1);
                $testsTaken = 1;
            }

            if ($testsTaken > 0) {
                if ($accuracy >= 60) {
                    $status = 'completed';
                    $subCompleted++;
                    $completedChaptersAcross++;
                } else {
                    $status = 'in_progress';
                    $inProgressChaptersAcross++;
                    // Flag as weak chapter if accuracy < 50
                    if ($accuracy < 50) {
                        $weakChapters[] = [
                            'chapter_id' => $chId,
                            'chapter_name' => $ch['chapter_name'],
                            'subject_name' => $sub['subject_name'],
                            'accuracy_percentage' => $accuracy,
                            'total_mcqs' => intval($ch['total_mcqs'])
                        ];
                    }
                }
            } else {
                $status = 'pending';
                $pendingChaptersAcross++;
            }

            $totalChaptersAcross++;

            $subChaptersList[] = [
                'chapter_id' => $chId,
                'chapter_name' => $ch['chapter_name'],
                'total_mcqs' => intval($ch['total_mcqs']),
                'status' => $status,
                'accuracy_percentage' => $accuracy,
                'tests_taken' => $testsTaken
            ];
        }

        $subPercent = $subTotal > 0 ? round(($subCompleted / $subTotal) * 100, 1) : 0;

        $subjectResults[] = [
            'subject_id' => $subId,
            'subject_name' => $sub['subject_name'],
            'total_chapters' => $subTotal,
            'completed_chapters' => $subCompleted,
            'pending_chapters' => $subTotal - $subCompleted,
            'completion_percentage' => $subPercent,
            'chapters' => $subChaptersList
        ];
    }

    // 3. Fetch Negative Basket (Unresolved wrong questions)
    // Make sure negative_basket exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `negative_basket` (
            `basket_id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `question_id` INT NOT NULL,
            `subject_name` VARCHAR(150) DEFAULT NULL,
            `chapter_name` VARCHAR(150) DEFAULT NULL,
            `chapter_id` INT DEFAULT NULL,
            `selected_answer` VARCHAR(100) DEFAULT NULL,
            `correct_answer` VARCHAR(100) DEFAULT NULL,
            `wrong_attempt_count` INT NOT NULL DEFAULT 1,
            `last_wrong_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `resolved` TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY `unique_student_question` (`student_id`, `question_id`),
            INDEX `idx_student` (`student_id`),
            INDEX `idx_resolved` (`resolved`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $stmtNeg = $pdo->prepare("
        SELECT nb.basket_id, nb.question_id, nb.subject_name, nb.chapter_name, nb.wrong_attempt_count,
               m.question, m.option_a, m.option_b, m.option_c, m.option_d, m.correct_answer, m.explanation
        FROM negative_basket nb
        JOIN mcqs m ON nb.question_id = m.mcq_id
        WHERE nb.student_id = ? AND nb.resolved = 0
        ORDER BY nb.last_wrong_date DESC
        LIMIT 30
    ");
    $stmtNeg->execute([$user_id]);
    $negativeQuestions = $stmtNeg->fetchAll(PDO::FETCH_ASSOC);
    $totalNegativeCount = count($negativeQuestions);

    // 4. Calculate Overall Scholarship Readiness Score
    // Readiness = (Syllabus Completion % * 0.5) + (Accuracy % on attempted questions * 0.5)
    $overallCompletionPct = $totalChaptersAcross > 0 
        ? round(($completedChaptersAcross / $totalChaptersAcross) * 100, 1) 
        : 0;

    // Average accuracy across all attempted chapters
    $stmtOverallAcc = $pdo->prepare("SELECT AVG(percentage) as avg_acc FROM student_progress WHERE user_id = ?");
    $stmtOverallAcc->execute([$user_id]);
    $avgAcc = floatval($stmtOverallAcc->fetchColumn() ?: 0);

    $readinessScore = round(($overallCompletionPct * 0.5) + ($avgAcc * 0.5), 1);
    if ($readinessScore == 0 && $overallCompletionPct > 0) {
        $readinessScore = round($overallCompletionPct * 0.6, 1);
    }

    sendResponse('success', 'Performance loaded successfully', [
        'readiness_percentage' => $readinessScore,
        'syllabus_summary' => [
            'total_chapters' => $totalChaptersAcross,
            'completed_chapters' => $completedChaptersAcross,
            'in_progress_chapters' => $inProgressChaptersAcross,
            'pending_chapters' => $pendingChaptersAcross,
            'completion_percentage' => $overallCompletionPct
        ],
        'subjects' => $subjectResults,
        'weak_chapters' => $weakChapters,
        'negative_basket' => [
            'total_count' => $totalNegativeCount,
            'questions' => $negativeQuestions
        ]
    ]);

} catch (Exception $e) {
    sendResponse('error', 'Error: ' . $e->getMessage(), null, 500);
}
