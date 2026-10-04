<?php
/**
 * Student Performance Report & Negative Questions API
 * Simple, Fast & Clean Unified Performance Service
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

$user_id = intval($_GET['user_id'] ?? ($_POST['user_id'] ?? 0));

if ($user_id <= 0) {
    sendResponse('error', 'Invalid user_id parameter', null, 400);
}

try {
    // 0. Ensure negative_basket table exists
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

    // 1. Fetch user's class_id
    $stmtUser = $pdo->prepare("SELECT class_id, board_type FROM users WHERE user_id = ?");
    $stmtUser->execute([$user_id]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $class_id = intval($userRow['class_id'] ?? 0);

    // 2. Aggregate Actual Activity
    // A. Chapter MCQ Attempts
    $stmtMcq = $pdo->prepare("
        SELECT 
            COUNT(*) as total_mcq_attempts,
            IFNULL(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END), 0) as mcq_correct,
            IFNULL(SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END), 0) as mcq_wrong,
            COUNT(DISTINCT DATE(attempted_at)) as mcq_active_days
        FROM mcq_attempts 
        WHERE user_id = ?
    ");
    $stmtMcq->execute([$user_id]);
    $mcqStats = $stmtMcq->fetch(PDO::FETCH_ASSOC);

    // B. Exam Attempts
    $stmtExams = $pdo->prepare("
        SELECT 
            COUNT(*) as total_exams,
            IFNULL(SUM(correct_count), 0) as exam_correct,
            IFNULL(SUM(wrong_count), 0) as exam_wrong,
            IFNULL(SUM(unattempted_count), 0) as exam_unattempted,
            IFNULL(SUM(positive_score), 0.00) as total_positive_marks,
            IFNULL(SUM(negative_deduction), 0.00) as total_negative_marks_lost,
            IFNULL(AVG(net_score), 0.00) as avg_net_score
        FROM exam_attempts
        WHERE user_id = ?
    ");
    $stmtExams->execute([$user_id]);
    $examStats = $stmtExams->fetch(PDO::FETCH_ASSOC);

    // C. Class Exams
    $stmtClassExams = $pdo->prepare("
        SELECT 
            COUNT(*) as total_class_exams,
            IFNULL(SUM(correct), 0) as class_correct,
            IFNULL(SUM(incorrect), 0) as class_wrong
        FROM class_exam_results
        WHERE user_id = ?
    ");
    $stmtClassExams->execute([$user_id]);
    $classExamStats = $stmtClassExams->fetch(PDO::FETCH_ASSOC);

    // D. Progress tests (from student_progress)
    $stmtSp = $pdo->prepare("
        SELECT 
            COUNT(*) as total_progress_tests,
            IFNULL(SUM(mcq_score), 0) as sp_correct,
            IFNULL(SUM(CASE WHEN total_mcq > mcq_score THEN (total_mcq - mcq_score) ELSE 0 END), 0) as sp_wrong
        FROM student_progress
        WHERE user_id = ?
    ");
    $stmtSp->execute([$user_id]);
    $spStats = $stmtSp->fetch(PDO::FETCH_ASSOC);

    // Consolidate Overall Counts
    $total_correct = intval($mcqStats['mcq_correct']) + intval($examStats['exam_correct']) + intval($classExamStats['class_correct']);
    $total_wrong = intval($mcqStats['mcq_wrong']) + intval($examStats['exam_wrong']) + intval($classExamStats['class_wrong']);
    $total_attempted = $total_correct + $total_wrong;

    // If only student_progress has records
    if ($total_attempted == 0 && intval($spStats['sp_correct']) + intval($spStats['sp_wrong']) > 0) {
        $total_correct = intval($spStats['sp_correct']);
        $total_wrong = intval($spStats['sp_wrong']);
        $total_attempted = $total_correct + $total_wrong;
    }

    $accuracy_pct = $total_attempted > 0 ? round(($total_correct / $total_attempted) * 100) : 0;
    
    // Total Tests Count
    $total_tests = intval($examStats['total_exams']) + intval($classExamStats['total_class_exams']) + intval($spStats['total_progress_tests']);
    if ($total_tests === 0 && intval($mcqStats['total_mcq_attempts']) > 0) {
        $total_tests = max(1, intval($mcqStats['mcq_active_days']));
    }

    // Negative Basket Count
    $stmtBasketCount = $pdo->prepare("SELECT COUNT(*) as c FROM negative_basket WHERE student_id = ? AND resolved = 0");
    $stmtBasketCount->execute([$user_id]);
    $negative_questions_count = intval($stmtBasketCount->fetch()['c'] ?? 0);

    // If negative_basket is empty but student had wrong answers in mcq_attempts or exams, auto-backfill on the fly
    if ($negative_questions_count === 0 && intval($mcqStats['mcq_wrong']) > 0) {
        try {
            $pdo->prepare("
                INSERT IGNORE INTO negative_basket 
                (student_id, question_id, subject_name, chapter_name, chapter_id, selected_answer, correct_answer, wrong_attempt_count, last_wrong_date, resolved)
                SELECT 
                    ma.user_id as student_id,
                    ma.mcq_id as question_id,
                    COALESCE(s.subject_name, 'General') as subject_name,
                    COALESCE(ch.chapter_name, 'Practice') as chapter_name,
                    ma.chapter_id,
                    ma.selected_answer,
                    ma.correct_answer,
                    COUNT(*) as wrong_count,
                    MAX(ma.attempted_at) as last_wrong,
                    0 as resolved
                FROM mcq_attempts ma
                LEFT JOIN chapters ch ON ma.chapter_id = ch.chapter_id
                LEFT JOIN subjects s ON ch.subject_id = s.subject_id
                WHERE ma.user_id = ? AND ma.is_correct = 0
                GROUP BY ma.user_id, ma.mcq_id
            ")->execute([$user_id]);

            $stmtBasketCount->execute([$user_id]);
            $negative_questions_count = intval($stmtBasketCount->fetch()['c'] ?? 0);
        } catch (Exception $e) {
            $stmtFallbackNeg = $pdo->prepare("SELECT COUNT(DISTINCT mcq_id) as c FROM mcq_attempts WHERE user_id = ? AND is_correct = 0");
            $stmtFallbackNeg->execute([$user_id]);
            $negative_questions_count = intval($stmtFallbackNeg->fetch()['c'] ?? 0);
        }
    }

    if ($negative_questions_count === 0 && intval($examStats['exam_wrong']) > 0) {
        try {
            $pdo->prepare("
                INSERT IGNORE INTO negative_basket 
                (student_id, question_id, subject_name, chapter_name, chapter_id, selected_answer, correct_answer, wrong_attempt_count, last_wrong_date, resolved)
                SELECT 
                    ea.user_id as student_id,
                    ea.mcq_id as question_id,
                    COALESCE(s.subject_name, 'General') as subject_name,
                    COALESCE(ch.chapter_name, 'Exam') as chapter_name,
                    ea.chapter_id,
                    ea.selected_option as selected_answer,
                    ea.correct_option as correct_answer,
                    COUNT(*) as wrong_count,
                    MAX(ea.created_at) as last_wrong,
                    0 as resolved
                FROM exam_attempt_answers ea
                LEFT JOIN chapters ch ON ea.chapter_id = ch.chapter_id
                LEFT JOIN subjects s ON ch.subject_id = s.subject_id
                WHERE ea.user_id = ? AND ea.is_correct = 0
                GROUP BY ea.user_id, ea.mcq_id
            ")->execute([$user_id]);

            $stmtBasketCount->execute([$user_id]);
            $negative_questions_count = intval($stmtBasketCount->fetch()['c'] ?? 0);
        } catch (Exception $e) {}
    }

    // Overall Status
    $overall_status = 'Good';
    if ($accuracy_pct < 50) {
        $overall_status = 'Weak';
    } elseif ($accuracy_pct < 75) {
        $overall_status = 'Average';
    }

    $overall_performance_pct = $accuracy_pct;
    $is_empty_state = ($total_attempted === 0);

    // 3. Subject-wise Performance
    // Fetch subjects for this student's class (or any subjects attempted)
    $subSql = "
        SELECT 
            s.subject_id,
            s.subject_name
        FROM subjects s
        WHERE s.class_id = ? OR s.class_id IS NULL OR s.class_id = 0
        ORDER BY s.subject_name ASC
    ";
    $stmtSub = $pdo->prepare($subSql);
    $stmtSub->execute([$class_id]);
    $subjectsList = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

    // If none found for class, fetch all distinct subjects
    if (empty($subjectsList)) {
        $subjectsList = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate subject accuracy
    $subjectPerformance = [];
    foreach ($subjectsList as $sub) {
        $subId = intval($sub['subject_id']);
        
        // Sum from mcq_attempts
        $stmtSubMcq = $pdo->prepare("
            SELECT 
                COUNT(*) as attempted,
                IFNULL(SUM(CASE WHEN ma.is_correct = 1 THEN 1 ELSE 0 END), 0) as correct,
                IFNULL(SUM(CASE WHEN ma.is_correct = 0 THEN 1 ELSE 0 END), 0) as wrong
            FROM mcq_attempts ma
            JOIN chapters ch ON ma.chapter_id = ch.chapter_id
            WHERE ma.user_id = ? AND ch.subject_id = ?
        ");
        $stmtSubMcq->execute([$user_id, $subId]);
        $subMcq = $stmtSubMcq->fetch(PDO::FETCH_ASSOC);

        // Sum from exam_attempt_answers
        $stmtSubExam = $pdo->prepare("
            SELECT 
                COUNT(*) as attempted,
                IFNULL(SUM(CASE WHEN ea.is_correct = 1 THEN 1 ELSE 0 END), 0) as correct,
                IFNULL(SUM(CASE WHEN ea.is_correct = 0 THEN 1 ELSE 0 END), 0) as wrong
            FROM exam_attempt_answers ea
            JOIN chapters ch ON ea.chapter_id = ch.chapter_id
            WHERE ea.user_id = ? AND ch.subject_id = ? AND ea.is_correct IN (0, 1)
        ");
        $stmtSubExam->execute([$user_id, $subId]);
        $subExam = $stmtSubExam->fetch(PDO::FETCH_ASSOC);

        $subAttempted = intval($subMcq['attempted']) + intval($subExam['attempted']);
        $subCorrect = intval($subMcq['correct']) + intval($subExam['correct']);
        $subWrong = intval($subMcq['wrong']) + intval($subExam['wrong']);

        $subAccuracy = $subAttempted > 0 ? round(($subCorrect / $subAttempted) * 100) : 0;
        
        $status = 'Average';
        $status_color = 'yellow';
        if ($subAttempted === 0) {
            $status = 'Not Started';
            $status_color = 'gray';
        } elseif ($subAccuracy >= 75) {
            $status = 'Strong';
            $status_color = 'green';
        } elseif ($subAccuracy >= 50) {
            $status = 'Average';
            $status_color = 'yellow';
        } else {
            $status = 'Weak';
            $status_color = 'red';
        }

        $subjectPerformance[] = [
            'subject_id' => $subId,
            'subject_name' => $sub['subject_name'],
            'accuracy_pct' => $subAccuracy,
            'total_attempted' => $subAttempted,
            'correct_count' => $subCorrect,
            'wrong_count' => $subWrong,
            'status' => $status,
            'status_color' => $status_color
        ];
    }

    // 4. Chapter Breakdown & Weak Chapters
    // Look at chapters the student has attempted
    $stmtChAttempts = $pdo->prepare("
        SELECT 
            ch.chapter_id,
            ch.chapter_name,
            ch.subject_id,
            s.subject_name,
            COUNT(*) as attempted,
            SUM(CASE WHEN ma.is_correct = 1 THEN 1 ELSE 0 END) as correct,
            SUM(CASE WHEN ma.is_correct = 0 THEN 1 ELSE 0 END) as wrong
        FROM mcq_attempts ma
        JOIN chapters ch ON ma.chapter_id = ch.chapter_id
        LEFT JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE ma.user_id = ?
        GROUP BY ch.chapter_id
        ORDER BY attempted DESC
    ");
    $stmtChAttempts->execute([$user_id]);
    $chRows = $stmtChAttempts->fetchAll(PDO::FETCH_ASSOC);

    $weak_chapters = [];
    $chapter_breakdown = [
        'strong_chapters' => [],
        'average_chapters' => [],
        'weak_chapters' => []
    ];

    foreach ($chRows as $row) {
        $att = intval($row['attempted']);
        $corr = intval($row['correct']);
        $wrg = intval($row['wrong']);
        $acc = $att > 0 ? round(($corr / $att) * 100) : 0;

        $item = [
            'chapter_id' => intval($row['chapter_id']),
            'chapter_name' => $row['chapter_name'],
            'subject_id' => intval($row['subject_id']),
            'subject_name' => $row['subject_name'] ?? 'General',
            'accuracy_pct' => $acc,
            'total_attempted' => $att,
            'correct_count' => $corr,
            'wrong_count' => $wrg,
            'status' => ($acc >= 75) ? 'Strong' : (($acc >= 50) ? 'Average' : 'Weak')
        ];

        if ($acc >= 75) {
            $chapter_breakdown['strong_chapters'][] = $item;
        } elseif ($acc >= 50) {
            $chapter_breakdown['average_chapters'][] = $item;
        } else {
            $chapter_breakdown['weak_chapters'][] = $item;
            $weak_chapters[] = $item;
        }
    }

    // 5. Incomplete Chapters Logic (0% Not Started, 1-99% In Progress, 100% Completed)
    // Query chapters for student's class
    $stmtAllChapters = $pdo->prepare("
        SELECT 
            ch.chapter_id,
            ch.chapter_name,
            ch.subject_id,
            s.subject_name,
            (SELECT COUNT(*) FROM mcqs WHERE chapter_id = ch.chapter_id) as total_mcqs,
            (SELECT COUNT(DISTINCT mcq_id) FROM mcq_attempts WHERE user_id = ? AND chapter_id = ch.chapter_id) as solved_mcqs,
            (SELECT COUNT(*) FROM videos WHERE chapter_id = ch.chapter_id) as total_videos,
            (SELECT COUNT(*) FROM notes WHERE chapter_id = ch.chapter_id) as total_notes
        FROM chapters ch
        JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE (s.class_id = ? OR ? = 0)
        ORDER BY ch.chapter_id ASC
        LIMIT 60
    ");
    $stmtAllChapters->execute([$user_id, $class_id, $class_id]);
    $allChapters = $stmtAllChapters->fetchAll(PDO::FETCH_ASSOC);

    $incomplete_chapters = [];
    foreach ($allChapters as $ch) {
        $totMcqs = intval($ch['total_mcqs']);
        $solvedMcqs = intval($ch['solved_mcqs']);
        
        $progress = 0;
        if ($totMcqs > 0) {
            $progress = round(($solvedMcqs / $totMcqs) * 100);
        } elseif ($solvedMcqs > 0) {
            $progress = 50;
        }

        if ($progress >= 100) {
            continue; // Completed, omit from incomplete list
        }

        $status = ($progress === 0) ? 'Not Started' : 'In Progress';
        $status_type = ($progress === 0) ? 'not_started' : 'in_progress';

        $incomplete_chapters[] = [
            'chapter_id' => intval($ch['chapter_id']),
            'chapter_name' => $ch['chapter_name'],
            'subject_id' => intval($ch['subject_id']),
            'subject_name' => $ch['subject_name'],
            'progress_pct' => $progress,
            'status' => $status,
            'status_type' => $status_type,
            'total_mcqs' => $totMcqs,
            'solved_mcqs' => $solvedMcqs
        ];
    }

    // Sort Incomplete chapters: In Progress (progress > 0) first, then Not Started (progress == 0)
    usort($incomplete_chapters, function($a, $b) {
        if ($a['progress_pct'] > 0 && $b['progress_pct'] == 0) return -1;
        if ($a['progress_pct'] == 0 && $b['progress_pct'] > 0) return 1;
        return $b['progress_pct'] - $a['progress_pct'];
    });

    // 6. Negative Basket Questions Detail
    $stmtBasket = $pdo->prepare("
        SELECT 
            nb.basket_id,
            nb.question_id,
            nb.subject_name,
            nb.chapter_name,
            nb.chapter_id,
            nb.selected_answer,
            nb.correct_answer,
            nb.wrong_attempt_count,
            nb.last_wrong_date,
            m.question,
            m.option_a,
            m.option_b,
            m.option_c,
            m.option_d,
            m.explanation
        FROM negative_basket nb
        LEFT JOIN mcqs m ON nb.question_id = m.mcq_id
        WHERE nb.student_id = ? AND nb.resolved = 0
        ORDER BY nb.wrong_attempt_count DESC, nb.last_wrong_date DESC
        LIMIT 50
    ");
    $stmtBasket->execute([$user_id]);
    $basketQuestions = $stmtBasket->fetchAll(PDO::FETCH_ASSOC);

    // 7. Study Next Recommendation (Exactly ONE main recommendation)
    $study_next = null;

    if ($negative_questions_count >= 5) {
        // High priority: Negative Questions revision
        $study_next = [
            'type' => 'revise_negative',
            'title' => 'Revise Negative Questions',
            'subtitle' => "{$negative_questions_count} questions waiting for revision.",
            'button_text' => 'Start Revision →',
            'action' => 'negative_basket'
        ];
    } elseif (!empty($weak_chapters)) {
        // High priority: Top Weak Chapter
        $topWeak = $weak_chapters[0];
        $study_next = [
            'type' => 'practice_chapter',
            'title' => "Practice {$topWeak['chapter_name']}",
            'subtitle' => "Accuracy: {$topWeak['accuracy_pct']}% • {$topWeak['subject_name']}",
            'button_text' => 'Practice MCQs →',
            'action' => 'chapter_mcq',
            'chapter' => $topWeak
        ];
    } elseif (!empty($incomplete_chapters)) {
        // High priority: Unfinished chapter in progress
        $topIncomplete = $incomplete_chapters[0];
        if ($topIncomplete['progress_pct'] > 0) {
            $study_next = [
                'type' => 'continue_chapter',
                'title' => "Continue {$topIncomplete['chapter_name']}",
                'subtitle' => "Progress: {$topIncomplete['progress_pct']}% • {$topIncomplete['subject_name']}",
                'button_text' => 'Continue Learning →',
                'action' => 'chapter_content',
                'chapter' => $topIncomplete
            ];
        } else {
            $study_next = [
                'type' => 'start_chapter',
                'title' => "Start {$topIncomplete['chapter_name']}",
                'subtitle' => "Not Started • {$topIncomplete['subject_name']}",
                'button_text' => 'Start Learning →',
                'action' => 'chapter_content',
                'chapter' => $topIncomplete
            ];
        }
    } elseif ($negative_questions_count > 0) {
        $study_next = [
            'type' => 'revise_negative',
            'title' => 'Revise Negative Questions',
            'subtitle' => "{$negative_questions_count} questions waiting for revision.",
            'button_text' => 'Start Revision →',
            'action' => 'negative_basket'
        ];
    } else {
        $study_next = [
            'type' => 'explore_subjects',
            'title' => 'Great Job! All Chapters Done',
            'subtitle' => 'Keep practicing MCQs and taking exams to master your subjects.',
            'button_text' => 'Explore Subjects →',
            'action' => 'subjects'
        ];
    }

    // Response structure
    $response_payload = [
        'is_empty_state' => $is_empty_state,
        'summary' => [
            'overall_performance_pct' => $overall_performance_pct,
            'accuracy_pct' => $accuracy_pct,
            'total_tests' => $total_tests,
            'negative_questions_count' => $negative_questions_count,
            'total_correct' => $total_correct,
            'total_wrong' => $total_wrong,
            'total_attempted' => $total_attempted,
            'overall_status' => $overall_status
        ],
        'overall_performance' => [
            'percentage' => $overall_performance_pct,
            'status' => $overall_status,
            'correct_count' => $total_correct,
            'wrong_count' => $total_wrong,
            'attempted_count' => $total_attempted
        ],
        'subjects' => $subjectPerformance,
        'weak_chapters' => $weak_chapters,
        'incomplete_chapters' => $incomplete_chapters,
        'negative_basket' => [
            'total_count' => $negative_questions_count,
            'status_label' => 'Needs Revision',
            'questions' => $basketQuestions
        ],
        'study_next' => $study_next,

        // Legacy / Backward compatibility keys
        'stats' => [
            'total_exams' => $total_tests,
            'avg_net_score' => round(floatval($examStats['avg_net_score']), 2),
            'overall_accuracy_pct' => $accuracy_pct,
            'total_negative_marks_lost' => round(floatval($examStats['total_negative_marks_lost']), 2),
            'total_positive_marks' => round(floatval($examStats['total_positive_marks']), 2),
            'total_correct' => $total_correct,
            'total_wrong' => $total_wrong,
            'total_unattempted' => intval($examStats['exam_unattempted'])
        ],
        'negative_questions' => $basketQuestions,
        'chapter_breakdown' => $chapter_breakdown
    ];

    sendResponse('success', 'Student performance report retrieved successfully', $response_payload);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
