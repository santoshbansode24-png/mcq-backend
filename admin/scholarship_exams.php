<?php
/**
 * Admin Dynamic Live Exam Creation & Management System
 * Veeru
 */
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once '../config/db.php';
if (file_exists('../config/ai_config.php')) {
    require_once '../config/ai_config.php';
} elseif (file_exists('../backend/config/ai_config.php')) {
    require_once '../backend/config/ai_config.php';
}

// Ensure exam_attempts table exists for Student Live Exam Database
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `exam_attempts` (
            `attempt_id` INT AUTO_INCREMENT PRIMARY KEY,
            `exam_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `total_questions` INT DEFAULT 0,
            `correct_count` INT DEFAULT 0,
            `wrong_count` INT DEFAULT 0,
            `unattempted_count` INT DEFAULT 0,
            `positive_score` DECIMAL(7,2) DEFAULT 0.00,
            `negative_deduction` DECIMAL(7,2) DEFAULT 0.00,
            `net_score` DECIMAL(7,2) DEFAULT 0.00,
            `max_possible_score` DECIMAL(7,2) DEFAULT 0.00,
            `accuracy_percentage` DECIMAL(5,2) DEFAULT 0.00,
            `time_spent_seconds` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`exam_id`),
            INDEX (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {}

// AJAX: Fetch Student Live Exam Database for a specific Exam
if (isset($_GET['ajax_students'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $eid = intval($_GET['ajax_students']);
    try {
        $stmt = $pdo->prepare("
            SELECT ea.attempt_id, ea.user_id, ea.net_score, ea.max_possible_score,
                   ea.correct_count, ea.wrong_count, ea.unattempted_count,
                   ea.accuracy_percentage, ea.time_spent_seconds, ea.created_at,
                   COALESCE(u.name, CONCAT('Student #', ea.user_id)) as student_name,
                   COALESCE(u.email, '') as email,
                   COALESCE(u.phone_number, u.mobile, '') as phone
            FROM exam_attempts ea
            LEFT JOIN users u ON ea.user_id = u.user_id
            WHERE ea.exam_id = ?
            ORDER BY ea.net_score DESC, ea.time_spent_seconds ASC, ea.attempt_id ASC
        ");
        $stmt->execute([$eid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $i => &$r) {
            $r['rank'] = $i + 1;
        }
        echo json_encode(['status' => 'success', 'data' => $rows], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// AJAX: Auto-Generate Missing MCQs with AI for a Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajax_generate_missing_mcqs') {
    header('Content-Type: application/json; charset=UTF-8');
    $subjectName = trim($_POST['subject_name'] ?? '');
    $linkedSubjectId = intval($_POST['linked_subject_id'] ?? 0);
    $subjectMapId = intval($_POST['subject_map_id'] ?? 0);
    $categorySlug = trim($_POST['category_slug'] ?? 'scholarship');
    $classId = intval($_POST['class_id'] ?? 38);
    $difficulty = trim($_POST['difficulty_level'] ?? 'mixed');
    $count = max(1, min(25, intval($_POST['count'] ?? 10)));

    try {
        if (empty($subjectName)) throw new Exception('Subject name is required.');
        if (!function_exists('callGeminiAPI')) throw new Exception('AI helper (callGeminiAPI) is not loaded.');

        $prompt = "Generate exactly {$count} high-quality competitive/scholarship exam MCQs in Marathi (or English if subject is English) for:\n"
                . "Exam Category: {$categorySlug}\nSubject: {$subjectName}\nDifficulty: {$difficulty}\n"
                . "Return ONLY valid JSON with key 'questions' as an array of objects: "
                . "{ \"question\": \"...\", \"option_a\": \"...\", \"option_b\": \"...\", \"option_c\": \"...\", \"option_d\": \"...\", \"correct_answer\": \"a|b|c|d\", \"explanation\": \"...\" }";

        $aiRaw = callGeminiAPI($prompt, ['temperature' => 0.4, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json']);
        $parsed = json_decode($aiRaw, true);
        if (!isset($parsed['questions']) && preg_match('/\{[\s\S]*\}/', $aiRaw, $m)) {
            $parsed = json_decode($m[0], true);
        }
        $questions = $parsed['questions'] ?? [];
        if (empty($questions)) throw new Exception('AI returned no questions.');

        // Resolve or create target subject_id
        $targetSubjectId = $linkedSubjectId;
        if ($targetSubjectId <= 0) {
            $stmtFindSub = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_name = ? LIMIT 1");
            $stmtFindSub->execute([$subjectName]);
            $targetSubjectId = intval($stmtFindSub->fetchColumn());
            if ($targetSubjectId <= 0) {
                $pdo->prepare("INSERT INTO subjects (subject_name, class_id) VALUES (?, ?)")->execute([$subjectName, $classId]);
                $targetSubjectId = intval($pdo->lastInsertId());
            }
        }
        if ($subjectMapId > 0 && $targetSubjectId > 0) {
            $pdo->prepare("UPDATE live_exam_type_subjects SET linked_subject_id = ? WHERE id = ?")->execute([$targetSubjectId, $subjectMapId]);
        }

        // Resolve or create default AI chapter
        $stmtFindCh = $pdo->prepare("SELECT chapter_id FROM chapters WHERE subject_id = ? ORDER BY chapter_id ASC LIMIT 1");
        $stmtFindCh->execute([$targetSubjectId]);
        $targetChapterId = intval($stmtFindCh->fetchColumn());
        if ($targetChapterId <= 0) {
            $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name) VALUES (?, ?)")->execute([$targetSubjectId, 'AI Live Exam Bank']);
            $targetChapterId = intval($pdo->lastInsertId());
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, category_slug)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $added = 0;
        foreach ($questions as $q) {
            if (empty($q['question'])) continue;
            $ans = strtolower(trim($q['correct_answer'] ?? 'a'));
            if (!in_array($ans, ['a', 'b', 'c', 'd'], true)) $ans = 'a';
            $stmtIns->execute([
                $targetChapterId,
                trim($q['question']),
                trim($q['option_a'] ?? ''),
                trim($q['option_b'] ?? ''),
                trim($q['option_c'] ?? ''),
                trim($q['option_d'] ?? ''),
                $ans,
                trim($q['explanation'] ?? ''),
                $categorySlug
            ]);
            $added++;
        }

        $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM mcqs m JOIN chapters ch ON m.chapter_id = ch.chapter_id WHERE ch.subject_id = ?");
        $stmtCnt->execute([$targetSubjectId]);
        $newTotal = intval($stmtCnt->fetchColumn());

        echo json_encode(['status' => 'success', 'added' => $added, 'subject_id' => $targetSubjectId, 'new_mcq_count' => $newTotal]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. SELF-HEALING SCHEMA & DYNAMIC EXAM/SUBJECT TABLES
// ─────────────────────────────────────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `exams` (
            `exam_id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `class_id` INT NOT NULL,
            `subject_id` INT DEFAULT NULL,
            `duration_minutes` INT DEFAULT 60,
            `positive_marks` DECIMAL(5,2) DEFAULT 2.00,
            `negative_marks` DECIMAL(5,2) DEFAULT 0.00,
            `total_questions` INT DEFAULT 0,
            `total_marks` DECIMAL(6,2) DEFAULT 100.00,
            `is_live` TINYINT(1) DEFAULT 0,
            `is_scholarship` TINYINT(1) DEFAULT 1,
            `category_slug` VARCHAR(50) DEFAULT 'scholarship',
            `exam_status` VARCHAR(20) DEFAULT 'scheduled',
            `difficulty_level` VARCHAR(20) DEFAULT 'mixed',
            `negative_marking_enabled` TINYINT(1) DEFAULT 0,
            `selected_subjects` TEXT DEFAULT NULL,
            `subject_distribution` TEXT DEFAULT NULL,
            `start_time` DATETIME DEFAULT NULL,
            `end_time` DATETIME DEFAULT NULL,
            `instructions` TEXT DEFAULT NULL,
            `question_ids` MEDIUMTEXT DEFAULT NULL,
            `selected_chapters` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`class_id`),
            INDEX (`is_live`),
            INDEX (`is_scholarship`),
            INDEX (`category_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $cols = $pdo->query("SHOW COLUMNS FROM `exams`")->fetchAll(PDO::FETCH_COLUMN);
    $needed = [
        'is_live' => "ALTER TABLE `exams` ADD COLUMN `is_live` TINYINT(1) DEFAULT 0",
        'is_scholarship' => "ALTER TABLE `exams` ADD COLUMN `is_scholarship` TINYINT(1) DEFAULT 1",
        'category_slug' => "ALTER TABLE `exams` ADD COLUMN `category_slug` VARCHAR(50) DEFAULT 'scholarship'",
        'total_marks' => "ALTER TABLE `exams` ADD COLUMN `total_marks` DECIMAL(6,2) DEFAULT 100.00",
        'exam_status' => "ALTER TABLE `exams` ADD COLUMN `exam_status` VARCHAR(20) DEFAULT 'scheduled'",
        'difficulty_level' => "ALTER TABLE `exams` ADD COLUMN `difficulty_level` VARCHAR(20) DEFAULT 'mixed'",
        'negative_marking_enabled' => "ALTER TABLE `exams` ADD COLUMN `negative_marking_enabled` TINYINT(1) DEFAULT 0",
        'selected_subjects' => "ALTER TABLE `exams` ADD COLUMN `selected_subjects` TEXT DEFAULT NULL",
        'subject_distribution' => "ALTER TABLE `exams` ADD COLUMN `subject_distribution` TEXT DEFAULT NULL",
        'start_time' => "ALTER TABLE `exams` ADD COLUMN `start_time` DATETIME DEFAULT NULL",
        'end_time' => "ALTER TABLE `exams` ADD COLUMN `end_time` DATETIME DEFAULT NULL",
        'instructions' => "ALTER TABLE `exams` ADD COLUMN `instructions` TEXT DEFAULT NULL",
        'question_ids' => "ALTER TABLE `exams` ADD COLUMN `question_ids` MEDIUMTEXT DEFAULT NULL",
        'selected_chapters' => "ALTER TABLE `exams` ADD COLUMN `selected_chapters` TEXT DEFAULT NULL"
    ];
    foreach ($needed as $col => $sql) {
        if (!in_array($col, $cols)) {
            try { $pdo->exec($sql); } catch (Exception $e) {}
        }
    }

    // Dynamic Exam Types Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `live_exam_types` (
            `exam_type_id` INT AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(60) NOT NULL UNIQUE,
            `name` VARCHAR(150) NOT NULL,
            `badge` VARCHAR(80) NOT NULL,
            `color` VARCHAR(20) DEFAULT '#4F46E5',
            `description` VARCHAR(255) DEFAULT '',
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Dynamic Exam Subjects Mapping Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `live_exam_type_subjects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `exam_type_id` INT NOT NULL,
            `subject_name` VARCHAR(150) NOT NULL,
            `linked_subject_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`exam_type_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed default Exam Types & Subjects if empty
    $typeCount = (int)$pdo->query("SELECT COUNT(*) FROM live_exam_types")->fetchColumn();
    if ($typeCount === 0) {
        $defaults = [
            [
                'slug' => 'navodaya',
                'name' => 'नवोदय प्रवेश परीक्षा (Navodaya Exam)',
                'badge' => '🏛️ नवोदय विद्यालय',
                'color' => '#0284C7',
                'description' => 'JNVST इयत्ता ६वी व ९वी प्रवेश परीक्षा',
                'subjects' => ['Mental Ability Test', 'Mathematics', 'English Grammar', 'Marathi Grammar']
            ],
            [
                'slug' => 'nmms',
                'name' => 'NMMS Exam',
                'badge' => '💡 NMMS प्रज्ञाशोध',
                'color' => '#7C3AED',
                'description' => 'राष्ट्रीय आर्थिक दुर्बल घटक शिष्यवृत्ती (MAT + SAT)',
                'subjects' => ['Mental Ability Test (MAT)', 'Mathematics', 'Science', 'Social Science']
            ],
            [
                'slug' => 'scholarship',
                'name' => 'Scholarship Exam',
                'badge' => '📜 राज्य शिष्यवृत्ती',
                'color' => '#4F46E5',
                'description' => 'महाराष्ट्र राज्य शिष्यवृत्ती इयत्ता ५वी (PUP) व ८वी (PSS)',
                'subjects' => ['Marathi (प्रथम भाषा)', 'Mathematics (गणित)', 'English (तृतीय भाषा)', 'Intelligence Test (बुद्धिमत्ता)']
            ],
            [
                'slug' => 'mts',
                'name' => 'MTS Exam',
                'badge' => '🌟 MTS प्रज्ञाशोध',
                'color' => '#9333EA',
                'description' => 'महाराष्ट्र टॅलेंट सर्च (MTS) परीक्षा',
                'subjects' => ['Mental Ability Test', 'Mathematics', 'General Science', 'Social Studies']
            ],
            [
                'slug' => 'sainik',
                'name' => 'सैनिक शाळा (Sainik School)',
                'badge' => '🎖️ सैनिक शाळा',
                'color' => '#15803D',
                'description' => 'AISSEE अखिल भारतीय सैनिक शाळा प्रवेश परीक्षा',
                'subjects' => ['Mathematics', 'Intelligence', 'Language', 'General Knowledge']
            ],
            [
                'slug' => 'olympiad',
                'name' => 'ऑलिम्पियाड (Olympiad)',
                'badge' => '🏆 ऑलिम्पियाड',
                'color' => '#D97706',
                'description' => 'SOF (IMO, NSO, IEO) व होमी भाभा स्पर्धा परीक्षा',
                'subjects' => ['Logical Reasoning', 'Mathematics', 'Science', 'English']
            ]
        ];

        $stmtInsType = $pdo->prepare("INSERT INTO live_exam_types (slug, name, badge, color, description) VALUES (?, ?, ?, ?, ?)");
        $stmtInsSub = $pdo->prepare("INSERT INTO live_exam_type_subjects (exam_type_id, subject_name) VALUES (?, ?)");
        foreach ($defaults as $d) {
            $stmtInsType->execute([$d['slug'], $d['name'], $d['badge'], $d['color'], $d['description']]);
            $tid = $pdo->lastInsertId();
            foreach ($d['subjects'] as $sub) {
                $stmtInsSub->execute([$tid, $sub]);
            }
        }
    }
} catch (Exception $e) {}

$message = '';
$error = '';

// Helper to pick MCQs based on linked subject_id, subject distribution, or chapters
function pickDynamicMCQs($pdo, $selected_subjects, $subject_distribution, $chapter_ids, $total_questions_limit, $difficulty_level, $category_slug = '') {
    $picked_mcq_ids = [];
    $diffSql = "";
    $diffParam = [];
    if (in_array($difficulty_level, ['easy', 'medium', 'hard'], true)) {
        static $hasDiffCol = null;
        if ($hasDiffCol === null) {
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM `mcqs`")->fetchAll(PDO::FETCH_COLUMN);
                $hasDiffCol = in_array('difficulty', $cols);
            } catch (Exception $e) {
                $hasDiffCol = false;
            }
        }
        if ($hasDiffCol) {
            $diffSql = " AND LOWER(COALESCE(m.difficulty, 'medium')) = ?";
            $diffParam = [$difficulty_level];
        }
    }

    // Lookup linked_subject_id map for this exam category
    $linkedMap = [];
    if (!empty($category_slug)) {
        try {
            $stmtMap = $pdo->prepare("
                SELECT lts.subject_name, lts.linked_subject_id
                FROM live_exam_type_subjects lts
                JOIN live_exam_types lt ON lts.exam_type_id = lt.exam_type_id
                WHERE lt.slug = ? AND lts.linked_subject_id IS NOT NULL AND lts.linked_subject_id > 0
            ");
            $stmtMap->execute([$category_slug]);
            foreach ($stmtMap->fetchAll(PDO::FETCH_ASSOC) as $mr) {
                $linkedMap[$mr['subject_name']] = intval($mr['linked_subject_id']);
            }
        } catch (Exception $e) {}
    }

    // 1. Pick by exact linked_subject_id first, then fallback to subject_name LIKE
    if (!empty($subject_distribution) && is_array($subject_distribution)) {
        foreach ($subject_distribution as $subName => $qCount) {
            $qCount = intval($qCount);
            if ($qCount <= 0) continue;

            if (!empty($linkedMap[$subName])) {
                $stmtSub = $pdo->prepare("
                    SELECT m.mcq_id
                    FROM mcqs m
                    JOIN chapters ch ON m.chapter_id = ch.chapter_id
                    WHERE ch.subject_id = ? $diffSql
                    ORDER BY RAND()
                    LIMIT $qCount
                ");
                $stmtSub->execute(array_merge([$linkedMap[$subName]], $diffParam));
            } else {
                $stmtSub = $pdo->prepare("
                    SELECT m.mcq_id
                    FROM mcqs m
                    JOIN chapters ch ON m.chapter_id = ch.chapter_id
                    JOIN subjects s ON ch.subject_id = s.subject_id
                    WHERE (s.subject_name = ? OR s.subject_name LIKE ?) $diffSql
                    ORDER BY RAND()
                    LIMIT $qCount
                ");
                $firstWord = explode(' ', trim($subName))[0];
                $stmtSub->execute(array_merge([$subName, "%$firstWord%"], $diffParam));
            }
            foreach ($stmtSub->fetchAll(PDO::FETCH_COLUMN) as $id) {
                if (!in_array($id, $picked_mcq_ids)) {
                    $picked_mcq_ids[] = $id;
                }
            }
        }
    }

    // 2. Supplement or pick from selected chapters if needed
    if (count($picked_mcq_ids) < $total_questions_limit && !empty($chapter_ids)) {
        $remaining = $total_questions_limit - count($picked_mcq_ids);
        $inPlaceholders = implode(',', array_fill(0, count($chapter_ids), '?'));
        $stmtMCQ = $pdo->prepare("SELECT mcq_id FROM mcqs WHERE chapter_id IN ($inPlaceholders) ORDER BY RAND() LIMIT $remaining");
        $stmtMCQ->execute($chapter_ids);
        foreach ($stmtMCQ->fetchAll(PDO::FETCH_COLUMN) as $id) {
            if (!in_array($id, $picked_mcq_ids)) {
                $picked_mcq_ids[] = $id;
            }
        }
    }

    return array_slice($picked_mcq_ids, 0, $total_questions_limit);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. POST ACTION HANDLERS
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_exam' || $action === 'update_exam') {
        $exam_id = intval($_POST['exam_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category_slug = trim($_POST['category_slug'] ?? 'scholarship');
        $class_id = intval($_POST['class_id'] ?? 38);
        $duration = max(1, intval($_POST['duration_minutes'] ?? 60));
        $total_marks = floatval($_POST['total_marks'] ?? 100);
        $total_questions_limit = max(1, intval($_POST['question_limit'] ?? 50));
        $pos_marks = floatval($_POST['positive_marks'] ?? ($total_marks / $total_questions_limit));
        $neg_enabled = isset($_POST['negative_marking_enabled']) && $_POST['negative_marking_enabled'] == '1' ? 1 : 0;
        $neg_marks = $neg_enabled ? floatval($_POST['negative_marks'] ?? 0.25) : 0.00;
        $difficulty_level = trim($_POST['difficulty_level'] ?? 'mixed');
        $exam_status = trim($_POST['exam_status'] ?? 'scheduled');
        $is_live = ($exam_status === 'draft') ? 0 : 1;

        $exam_date = trim($_POST['exam_date'] ?? '');
        $start_time_only = trim($_POST['start_time_only'] ?? '');
        $end_time_only = trim($_POST['end_time_only'] ?? '');
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : ($exam_date && $start_time_only ? "$exam_date $start_time_only:00" : null);
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : ($exam_date && $end_time_only ? "$exam_date $end_time_only:00" : null);

        $instructions = trim($_POST['instructions'] ?? '');
        $chapter_ids = $_POST['chapter_ids'] ?? [];
        $selected_subjects = $_POST['selected_subjects'] ?? [];
        $subject_dist_raw = $_POST['subject_dist'] ?? [];

        $subject_distribution = [];
        foreach ($selected_subjects as $subName) {
            $subject_distribution[$subName] = intval($subject_dist_raw[$subName] ?? 0);
        }

        if (empty($title)) {
            $error = 'Exam Title is required.';
        } elseif (empty($selected_subjects)) {
            $error = 'कृपया किमान एक विषय (Subject) निवडा.';
        } else {
            try {
                $picked_mcq_ids = pickDynamicMCQs($pdo, $selected_subjects, $subject_distribution, $chapter_ids, $total_questions_limit, $difficulty_level, $category_slug);
                $question_ids_str = implode(',', $picked_mcq_ids);
                $chapter_ids_str = implode(',', $chapter_ids);
                $total_q = count($picked_mcq_ids) > 0 ? count($picked_mcq_ids) : $total_questions_limit;
                $subjects_json = json_encode(array_values($selected_subjects), JSON_UNESCAPED_UNICODE);
                $dist_json = json_encode($subject_distribution, JSON_UNESCAPED_UNICODE);

                if ($action === 'update_exam' && $exam_id > 0) {
                    $stmtUpd = $pdo->prepare("
                        UPDATE exams SET
                            title = ?, class_id = ?, category_slug = ?, duration_minutes = ?,
                            positive_marks = ?, negative_marks = ?, total_questions = ?, total_marks = ?,
                            is_live = ?, exam_status = ?, difficulty_level = ?, negative_marking_enabled = ?,
                            selected_subjects = ?, subject_distribution = ?,
                            start_time = ?, end_time = ?, instructions = ?, question_ids = ?, selected_chapters = ?
                        WHERE exam_id = ?
                    ");
                    $stmtUpd->execute([
                        $title, $class_id, $category_slug, $duration,
                        $pos_marks, $neg_marks, $total_q, $total_marks,
                        $is_live, $exam_status, $difficulty_level, $neg_enabled,
                        $subjects_json, $dist_json,
                        $start_time, $end_time, $instructions, $question_ids_str, $chapter_ids_str,
                        $exam_id
                    ]);
                    $message = "Live Exam '$title' यशस्वीरित्या अपडेट झाले!";
                } else {
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO exams
                        (title, class_id, category_slug, duration_minutes, positive_marks, negative_marks, total_questions, total_marks, is_live, is_scholarship, exam_status, difficulty_level, negative_marking_enabled, selected_subjects, subject_distribution, start_time, end_time, instructions, question_ids, selected_chapters, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmtInsert->execute([
                        $title, $class_id, $category_slug, $duration, $pos_marks, $neg_marks, $total_q, $total_marks, $is_live,
                        $exam_status, $difficulty_level, $neg_enabled, $subjects_json, $dist_json,
                        $start_time, $end_time, $instructions, $question_ids_str, $chapter_ids_str
                    ]);
                    $message = "Live Exam '$title' ($total_marks गुण | $total_q प्रश्न) यशस्वीरित्या तयार झाले!";
                }
            } catch (Exception $e) {
                $error = "Failed to save exam: " . $e->getMessage();
            }
        }
    } elseif ($action === 'duplicate_exam') {
        $exam_id = intval($_POST['exam_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT * FROM exams WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $orig = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($orig) {
                $stmtDup = $pdo->prepare("
                    INSERT INTO exams
                    (title, class_id, category_slug, duration_minutes, positive_marks, negative_marks, total_questions, total_marks, is_live, is_scholarship, exam_status, difficulty_level, negative_marking_enabled, selected_subjects, subject_distribution, start_time, end_time, instructions, question_ids, selected_chapters, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtDup->execute([
                    $orig['title'] . ' (Copy)', $orig['class_id'], $orig['category_slug'], $orig['duration_minutes'],
                    $orig['positive_marks'], $orig['negative_marks'], $orig['total_questions'], $orig['total_marks'] ?? 100,
                    $orig['difficulty_level'] ?? 'mixed', $orig['negative_marking_enabled'] ?? 0,
                    $orig['selected_subjects'], $orig['subject_distribution'],
                    $orig['start_time'], $orig['end_time'], $orig['instructions'], $orig['question_ids'], $orig['selected_chapters']
                ]);
                $message = "Exam duplicated as Draft.";
            }
        } catch (Exception $e) {
            $error = "Duplicate error: " . $e->getMessage();
        }
    } elseif ($action === 'publish_exam') {
        $exam_id = intval($_POST['exam_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE exams SET is_live = 1, exam_status = 'live' WHERE exam_id = ?")->execute([$exam_id]);
            $message = "Exam published & set to LIVE!";
        } catch (Exception $e) {
            $error = "Publish error: " . $e->getMessage();
        }
    } elseif ($action === 'toggle_live') {
        $exam_id = intval($_POST['exam_id'] ?? 0);
        $current_status = intval($_POST['current_status'] ?? 0);
        $new_status = $current_status ? 0 : 1;
        $new_exam_status = $new_status ? 'scheduled' : 'draft';
        try {
            $pdo->prepare("UPDATE exams SET is_live = ?, exam_status = ? WHERE exam_id = ?")->execute([$new_status, $new_exam_status, $exam_id]);
            $message = "Exam status updated.";
        } catch (Exception $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    } elseif ($action === 'delete_exam') {
        $exam_id = intval($_POST['exam_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM exams WHERE exam_id = ?")->execute([$exam_id]);
            $message = "Exam deleted successfully.";
        } catch (Exception $e) {
            $error = "Error deleting exam: " . $e->getMessage();
        }
    } elseif ($action === 'save_exam_type') {
        $tid = intval($_POST['exam_type_id'] ?? 0);
        $name = trim($_POST['type_name'] ?? '');
        $badge = trim($_POST['type_badge'] ?? '📘 Exam');
        $color = trim($_POST['type_color'] ?? '#4F46E5');
        $desc = trim($_POST['type_desc'] ?? '');
        $slug = trim($_POST['type_slug'] ?? '');
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
        }
        if (!empty($name)) {
            try {
                if ($tid > 0) {
                    $pdo->prepare("UPDATE live_exam_types SET name=?, badge=?, color=?, description=? WHERE exam_type_id=?")
                        ->execute([$name, $badge, $color, $desc, $tid]);
                    $message = "Exam type '$name' updated!";
                } else {
                    $pdo->prepare("INSERT INTO live_exam_types (slug, name, badge, color, description) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$slug, $name, $badge, $color, $desc]);
                    $message = "New Exam type '$name' added!";
                }
            } catch (Exception $e) {
                $error = "Exam type error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_exam_type') {
        $tid = intval($_POST['exam_type_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM live_exam_type_subjects WHERE exam_type_id = ?")->execute([$tid]);
            $pdo->prepare("DELETE FROM live_exam_types WHERE exam_type_id = ?")->execute([$tid]);
            $message = "Exam type deleted.";
        } catch (Exception $e) {
            $error = "Delete exam type error: " . $e->getMessage();
        }
    } elseif ($action === 'save_exam_subject') {
        $sid = intval($_POST['subject_map_id'] ?? 0);
        $tid = intval($_POST['exam_type_id'] ?? 0);
        $sub_name = trim($_POST['subject_name'] ?? '');
        $linked_sub_id = intval($_POST['linked_subject_id'] ?? 0);
        $linkedVal = $linked_sub_id > 0 ? $linked_sub_id : null;
        if ($tid > 0 && !empty($sub_name)) {
            try {
                if ($sid > 0) {
                    $pdo->prepare("UPDATE live_exam_type_subjects SET subject_name=?, linked_subject_id=? WHERE id=?")->execute([$sub_name, $linkedVal, $sid]);
                    $message = "Subject '$sub_name' updated!";
                } else {
                    $pdo->prepare("INSERT INTO live_exam_type_subjects (exam_type_id, subject_name, linked_subject_id) VALUES (?, ?, ?)")->execute([$tid, $sub_name, $linkedVal]);
                    $message = "Subject '$sub_name' added!";
                }
            } catch (Exception $e) {
                $error = "Subject save error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_exam_subject') {
        $sid = intval($_POST['subject_map_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM live_exam_type_subjects WHERE id = ?")->execute([$sid]);
            $message = "Subject removed from exam.";
        } catch (Exception $e) {
            $error = "Delete subject error: " . $e->getMessage();
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. LOAD DYNAMIC DATA FOR DASHBOARD & WIZARD
// ─────────────────────────────────────────────────────────────────────────────
$classes = [];
try {
    $classes = $pdo->query("SELECT class_id, class_name FROM classes ORDER BY class_id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$chapters = [];
try {
    $chapters = $pdo->query("
        SELECT ch.chapter_id, ch.chapter_name, s.subject_name, c.class_name,
               (SELECT COUNT(*) FROM mcqs m WHERE m.chapter_id = ch.chapter_id) as mcq_count
        FROM chapters ch
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        ORDER BY s.subject_name, ch.chapter_name
        LIMIT 150
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// All DB Subjects with live MCQ count for exact Subject-to-MCQ linking
$allDbSubjects = [];
try {
    $allDbSubjects = $pdo->query("
        SELECT s.subject_id, s.subject_name, c.class_name,
               (SELECT COUNT(*) FROM mcqs m JOIN chapters ch ON m.chapter_id = ch.chapter_id WHERE ch.subject_id = s.subject_id) as mcq_count
        FROM subjects s
        LEFT JOIN classes c ON s.class_id = c.class_id
        ORDER BY s.subject_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$examTypes = [];
$CATEGORIES = [];
try {
    $rows = $pdo->query("SELECT * FROM live_exam_types WHERE is_active = 1 ORDER BY exam_type_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $subRows = $pdo->prepare("SELECT id, subject_name, linked_subject_id FROM live_exam_type_subjects WHERE exam_type_id = ? ORDER BY id ASC");
        $subRows->execute([$r['exam_type_id']]);
        $subsList = $subRows->fetchAll(PDO::FETCH_ASSOC);
        foreach ($subsList as &$sr) {
            if (!empty($sr['linked_subject_id'])) {
                $stC = $pdo->prepare("SELECT COUNT(*) FROM mcqs m JOIN chapters ch ON m.chapter_id = ch.chapter_id WHERE ch.subject_id = ?");
                $stC->execute([intval($sr['linked_subject_id'])]);
                $sr['available_mcqs'] = intval($stC->fetchColumn());
            } else {
                $fw = explode(' ', trim($sr['subject_name']))[0];
                $stC = $pdo->prepare("SELECT COUNT(*) FROM mcqs m JOIN chapters ch ON m.chapter_id = ch.chapter_id JOIN subjects s ON ch.subject_id = s.subject_id WHERE s.subject_name = ? OR s.subject_name LIKE ?");
                $stC->execute([$sr['subject_name'], "%$fw%"]);
                $sr['available_mcqs'] = intval($stC->fetchColumn());
            }
        }
        $r['subjects'] = $subsList;
        $examTypes[] = $r;
        $CATEGORIES[$r['slug']] = [
            'name' => $r['name'],
            'badge' => $r['badge'],
            'color' => $r['color'] ?: '#4F46E5',
            'desc' => $r['description']
        ];
    }
} catch (Exception $e) {}

$selected_cat = $_GET['cat'] ?? 'all';
$selected_status = $_GET['status'] ?? 'all';

$exams = [];
try {
    $whereClause = "e.is_scholarship = 1";
    $params = [];
    if ($selected_cat !== 'all') {
        $whereClause .= " AND e.category_slug = ?";
        $params[] = $selected_cat;
    }
    $stmt = $pdo->prepare("
        SELECT e.*, c.class_name,
               (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id) as total_attempts,
               (SELECT AVG(ea.net_score) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id) as avg_score
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.class_id
        WHERE $whereClause
        ORDER BY e.exam_id DESC
    ");
    $stmt->execute($params);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Compute effective status for each exam
$nowTs = time();
$statusCounts = ['all' => 0, 'live' => 0, 'upcoming' => 0, 'completed' => 0, 'draft' => 0];
$filteredExams = [];
foreach ($exams as $ex) {
    $st = $ex['exam_status'] ?? ($ex['is_live'] ? 'scheduled' : 'draft');
    if ($ex['is_live'] == 0 || $st === 'draft') {
        $effStatus = 'draft';
    } elseif ($st === 'completed' || (!empty($ex['end_time']) && strtotime($ex['end_time']) < $nowTs)) {
        $effStatus = 'completed';
    } elseif (!empty($ex['start_time']) && strtotime($ex['start_time']) > $nowTs) {
        $effStatus = 'upcoming';
    } else {
        $effStatus = 'live';
    }
    $ex['effective_status'] = $effStatus;
    $statusCounts['all']++;
    $statusCounts[$effStatus]++;
    if ($selected_status === 'all' || $selected_status === $effStatus) {
        $filteredExams[] = $ex;
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Live Exam System - Veeru Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f1f5f9; color: #1e293b; }
        .header { background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: white; padding: 18px 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 4px 16px rgba(79,70,229,0.2); }
        .header h1 { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-back { background: rgba(255,255,255,0.18); color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid rgba(255,255,255,0.25); cursor: pointer; }
        .nav { background: white; padding: 0 32px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
        .nav ul { list-style: none; display: flex; gap: 8px; overflow-x: auto; }
        .nav li a { display: block; padding: 14px 16px; color: #64748b; text-decoration: none; font-weight: 500; font-size: 14px; border-bottom: 3px solid transparent; white-space: nowrap; }
        .nav li a.active, .nav li a:hover { color: #4F46E5; border-bottom-color: #4F46E5; }
        .container { max-width: 1400px; margin: 24px auto; padding: 0 24px; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 18px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 14px; }
        .btn-create { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 12px 22px; border-radius: 10px; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; box-shadow: 0 4px 14px rgba(220,38,38,0.3); transition: 0.2s; }
        .btn-create:hover { transform: translateY(-1px); }
        .btn-manage { background: #4F46E5; color: white; padding: 12px 18px; border-radius: 10px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }

        /* Status & Category Tabs */
        .status-tabs { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .status-tab { padding: 9px 16px; border-radius: 10px; background: white; color: #475569; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 6px; }
        .status-tab.active { background: #1e293b; color: white; border-color: #1e293b; }
        .cat-filter-tabs { display: flex; gap: 8px; margin-bottom: 22px; overflow-x: auto; padding-bottom: 4px; }
        .cat-tab { padding: 8px 16px; border-radius: 50px; background: white; color: #475569; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid #e2e8f0; white-space: nowrap; }
        .cat-tab.active { background: #4F46E5; color: white; border-color: #4F46E5; }

        /* Exam Cards */
        .exams-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px; }
        .exam-card { background: white; border-radius: 16px; padding: 22px; box-shadow: 0 4px 18px rgba(15,23,42,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; border-top: 5px solid #4F46E5; }
        .exam-card.status-live { border-top-color: #10b981; }
        .exam-card.status-upcoming { border-top-color: #3b82f6; }
        .exam-card.status-completed { border-top-color: #64748b; }
        .exam-card.status-draft { border-top-color: #f59e0b; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .badge-live { background: #dcfce7; color: #15803d; }
        .badge-upcoming { background: #dbeafe; color: #1d4ed8; }
        .badge-completed { background: #f1f5f9; color: #475569; }
        .badge-draft { background: #fef3c7; color: #b45309; }
        .exam-title { font-size: 17px; font-weight: 700; color: #0f172a; margin: 10px 0 8px; }
        .subject-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .subject-chip { background: #eef2ff; color: #4338ca; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .exam-meta { font-size: 12.5px; color: #475569; display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); background: #f8fafc; padding: 10px; border-radius: 10px; text-align: center; margin-bottom: 14px; gap: 6px; }
        .stat-item .num { font-size: 16px; font-weight: 700; color: #0f172a; }
        .stat-item .label { font-size: 11px; color: #64748b; }
        .card-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-action { padding: 7px 11px; border-radius: 8px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; color: #334155; }
        .btn-action:hover { background: #e2e8f0; }
        .btn-publish { background: #10b981; color: white; }
        .btn-delete { background: #fee2e2; color: #b91c1c; }

        /* Modal & Wizard */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 1000; overflow-y: auto; padding: 24px 14px; }
        .modal-content { background: white; max-width: 820px; width: 100%; margin: 0 auto; border-radius: 18px; padding: 26px 28px; box-shadow: 0 24px 60px rgba(0,0,0,0.28); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 18px; }
        .modal-header h2 { font-size: 19px; color: #0f172a; }
        .close-btn { font-size: 24px; cursor: pointer; color: #64748b; border: none; background: none; padding: 2px 8px; border-radius: 8px; }
        .close-btn:hover { background: #f1f5f9; }

        /* Stepper */
        .stepper { display: flex; justify-content: space-between; gap: 6px; margin-bottom: 22px; overflow-x: auto; padding-bottom: 4px; }
        .step-pill { flex: 1; min-width: 110px; padding: 8px 10px; border-radius: 10px; background: #f1f5f9; color: #64748b; font-size: 12px; font-weight: 600; text-align: center; cursor: pointer; border: 1.5px solid transparent; }
        .step-pill.active { background: #eef2ff; color: #4F46E5; border-color: #4F46E5; }
        .step-pill.done { background: #dcfce7; color: #15803d; }
        .wizard-step { display: none; }
        .wizard-step.active { display: block; }

        .choice-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .choice-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 14px; cursor: pointer; transition: 0.15s; background: #fff; }
        .choice-card:hover { border-color: #818cf8; }
        .choice-card.selected { border-color: #4F46E5; background: #eef2ff; }
        .marks-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 16px; }
        .marks-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 14px 8px; text-align: center; cursor: pointer; font-weight: 700; font-size: 15px; }
        .marks-card.selected { border-color: #4F46E5; background: #4F46E5; color: white; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #334155; }
        .form-control { width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #4F46E5; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .dist-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 8px; }
        .wizard-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 16px; margin-top: 18px; gap: 10px; }
        .btn-secondary { padding: 10px 18px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; font-weight: 600; cursor: pointer; }
        .btn-primary { padding: 10px 20px; border-radius: 8px; border: none; background: #4F46E5; color: white; font-weight: 600; cursor: pointer; }
        @media (max-width: 640px) {
            .form-row, .marks-grid { grid-template-columns: 1fr 1fr; }
            .exams-grid { grid-template-columns: 1fr; }
            .modal-content { padding: 18px; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>🏆 Dynamic Live Exam व्यवस्थापन</h1>
    <div class="header-actions">
        <button onclick="openManageModal()" class="btn-back">⚙️ परीक्षा व विषय Manage करा</button>
        <a href="dashboard.php" class="btn-back">⬅ मुख्य डॅशबोर्ड</a>
    </div>
</div>

<nav class="nav">
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="scholarship_hub.php" style="color: #7C3AED; font-weight: 700;">✨ Scholarship Studio (AI)</a></li>
        <li><a href="scholarship_exams.php" class="active">🏆 Dynamic Live Exams</a></li>
        <li><a href="mcqs.php">MCQs</a></li>
        <li><a href="subjects.php">Subjects</a></li>
        <li><a href="chapters.php">Chapters</a></li>
        <li><a href="users.php">Users</a></li>
    </ul>
</nav>

<div class="container">
    <?php if (!empty($message)): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="top-bar">
        <div>
            <h2 style="font-size: 21px;">Live Exam Dashboard</h2>
            <p style="font-size: 13px; color: #64748b;">Dynamic परीक्षा निवडा, विषय सेट करा, Marks व Question Distribution ठरवा आणि Live Publish करा.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button onclick="openManageModal()" class="btn-manage">📚 Exams & Subjects</button>
            <button onclick="openCreateWizard()" class="btn-create">➕ Create Live Exam</button>
        </div>
    </div>

    <!-- KPI Summary Strip -->
    <?php $totalAllAttempts = array_sum(array_map(fn($x) => intval($x['total_attempts'] ?? 0), $exams)); ?>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:20px;">
        <div style="background:white;padding:16px;border-radius:14px;border:1px solid #e2e8f0;border-left:4px solid #4F46E5;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">एकूण परीक्षा (Total)</div>
            <div style="font-size:24px;font-weight:700;color:#0f172a;"><?php echo $statusCounts['all']; ?></div>
        </div>
        <div style="background:white;padding:16px;border-radius:14px;border:1px solid #e2e8f0;border-left:4px solid #10b981;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">🔴 सध्या Live</div>
            <div style="font-size:24px;font-weight:700;color:#15803d;"><?php echo $statusCounts['live']; ?></div>
        </div>
        <div style="background:white;padding:16px;border-radius:14px;border:1px solid #e2e8f0;border-left:4px solid #3b82f6;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">📅 Upcoming</div>
            <div style="font-size:24px;font-weight:700;color:#1d4ed8;"><?php echo $statusCounts['upcoming']; ?></div>
        </div>
        <div style="background:white;padding:16px;border-radius:14px;border:1px solid #e2e8f0;border-left:4px solid #7C3AED;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">👥 विद्यार्थी सहभाग</div>
            <div style="font-size:24px;font-weight:700;color:#6d28d9;"><?php echo $totalAllAttempts; ?></div>
        </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="status-tabs">
        <a href="?cat=<?php echo urlencode($selected_cat); ?>&status=all" class="status-tab <?php echo $selected_status === 'all' ? 'active' : ''; ?>">All (<?php echo $statusCounts['all']; ?>)</a>
        <a href="?cat=<?php echo urlencode($selected_cat); ?>&status=live" class="status-tab <?php echo $selected_status === 'live' ? 'active' : ''; ?>">🔴 Live Exams (<?php echo $statusCounts['live']; ?>)</a>
        <a href="?cat=<?php echo urlencode($selected_cat); ?>&status=upcoming" class="status-tab <?php echo $selected_status === 'upcoming' ? 'active' : ''; ?>">📅 Upcoming Exams (<?php echo $statusCounts['upcoming']; ?>)</a>
        <a href="?cat=<?php echo urlencode($selected_cat); ?>&status=completed" class="status-tab <?php echo $selected_status === 'completed' ? 'active' : ''; ?>">✅ Completed Exams (<?php echo $statusCounts['completed']; ?>)</a>
        <a href="?cat=<?php echo urlencode($selected_cat); ?>&status=draft" class="status-tab <?php echo $selected_status === 'draft' ? 'active' : ''; ?>">📝 Draft Exams (<?php echo $statusCounts['draft']; ?>)</a>
    </div>

    <!-- Category Filter Pills -->
    <div class="cat-filter-tabs">
        <a href="?cat=all&status=<?php echo urlencode($selected_status); ?>" class="cat-tab <?php echo $selected_cat === 'all' ? 'active' : ''; ?>">🌟 सर्व परीक्षा</a>
        <?php foreach ($examTypes as $et): ?>
            <a href="?cat=<?php echo urlencode($et['slug']); ?>&status=<?php echo urlencode($selected_status); ?>" class="cat-tab <?php echo $selected_cat === $et['slug'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($et['badge']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($filteredExams)): ?>
        <div style="text-align: center; padding: 50px 20px; background: white; border-radius: 16px; border: 1px solid #e2e8f0;">
            <div style="font-size: 44px; margin-bottom: 12px;">🏆</div>
            <h3>या विभागात सध्या कोणतीही परीक्षा नाही</h3>
            <p style="color: #64748b; font-size: 13px; margin: 8px 0 18px;">नवीन Dynamic Live Exam तयार करण्यासाठी खालील बटनावर क्लिक करा.</p>
            <button onclick="openCreateWizard()" class="btn-create">➕ Create Live Exam</button>
        </div>
    <?php else: ?>
        <div class="exams-grid">
            <?php foreach ($filteredExams as $ex):
                $eff = $ex['effective_status'];
                $catMeta = $CATEGORIES[$ex['category_slug']] ?? ['badge' => '📜 Exam', 'color' => '#4F46E5'];
                $subs = json_decode($ex['selected_subjects'] ?? '[]', true) ?: [];
                $totMarks = floatval($ex['total_marks'] ?? ($ex['total_questions'] * $ex['positive_marks']));
                $exJson = htmlspecialchars(json_encode($ex, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="exam-card status-<?php echo $eff; ?>">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                        <span class="badge badge-<?php echo $eff; ?>">
                            <?php echo $eff === 'live' ? '🔴 LIVE' : ($eff === 'upcoming' ? '📅 UPCOMING' : ($eff === 'completed' ? '✅ COMPLETED' : '📝 DRAFT')); ?>
                        </span>
                        <span style="font-size:11px; font-weight:700; color:<?php echo $catMeta['color']; ?>; background:#f8fafc; padding:3px 9px; border-radius:50px; border:1px solid #e2e8f0;">
                            <?php echo htmlspecialchars($catMeta['badge']); ?>
                        </span>
                    </div>

                    <h3 class="exam-title"><?php echo htmlspecialchars($ex['title']); ?></h3>

                    <?php if (!empty($subs)): ?>
                        <div class="subject-chips">
                            <?php foreach ($subs as $s): ?>
                                <span class="subject-chip"><?php echo htmlspecialchars($s); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="exam-meta">
                        <span>📅 Date: <?php echo $ex['start_time'] ? date('d M Y', strtotime($ex['start_time'])) : 'Anytime'; ?> | ⏰ <?php echo $ex['start_time'] ? date('h:i A', strtotime($ex['start_time'])) : '-'; ?> - <?php echo $ex['end_time'] ? date('h:i A', strtotime($ex['end_time'])) : '-'; ?></span>
                        <span>⏱️ Duration: <?php echo intval($ex['duration_minutes']); ?> मिनिटे | 🎯 Difficulty: <?php echo ucfirst($ex['difficulty_level'] ?? 'mixed'); ?></span>
                    </div>

                    <div class="stats-row">
                        <div class="stat-item"><div class="num"><?php echo intval($totMarks); ?></div><div class="label">Marks</div></div>
                        <div class="stat-item"><div class="num"><?php echo intval($ex['total_questions']); ?></div><div class="label">Questions</div></div>
                        <div class="stat-item"><div class="num">+<?php echo $ex['positive_marks']; ?>/-<?php echo $ex['negative_marks']; ?></div><div class="label">Scheme</div></div>
                        <div class="stat-item"><div class="num"><?php echo intval($ex['total_attempts']); ?></div><div class="label">Students</div></div>
                    </div>
                </div>

                <div class="card-actions">
                    <button type="button" onclick='openStudentsModal(<?php echo $ex['exam_id']; ?>, <?php echo json_encode($ex['title'], JSON_UNESCAPED_UNICODE); ?>)' class="btn-action" style="background:#eef2ff;color:#4338ca;">👥 Students (<?php echo intval($ex['total_attempts']); ?>)</button>
                    <button type="button" onclick='viewExamDetails(<?php echo $exJson; ?>)' class="btn-action">👁️ View</button>
                    <button type="button" onclick='editExamWizard(<?php echo $exJson; ?>)' class="btn-action">✏️ Edit</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="duplicate_exam">
                        <input type="hidden" name="exam_id" value="<?php echo $ex['exam_id']; ?>">
                        <button type="submit" class="btn-action">📋 Duplicate</button>
                    </form>
                    <?php if ($eff !== 'live'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="publish_exam">
                            <input type="hidden" name="exam_id" value="<?php echo $ex['exam_id']; ?>">
                            <button type="submit" class="btn-action btn-publish">🚀 Publish</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_live">
                            <input type="hidden" name="exam_id" value="<?php echo $ex['exam_id']; ?>">
                            <input type="hidden" name="current_status" value="1">
                            <button type="submit" class="btn-action">⏸️ Draft</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('ही परीक्षा कायमची डिलीट करायची का?');">
                        <input type="hidden" name="action" value="delete_exam">
                        <input type="hidden" name="exam_id" value="<?php echo $ex['exam_id']; ?>">
                        <button type="submit" class="btn-action btn-delete">🗑️ Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 5-STEP DYNAMIC LIVE EXAM WIZARD MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="wizardModalTitle">➕ नवीन Dynamic Live Exam तयार करा</h2>
            <button onclick="closeModal('createModal')" class="close-btn">&times;</button>
        </div>

        <div class="stepper">
            <div class="step-pill active" onclick="goToStep(1)" id="pill-1">1. परीक्षा निवडा</div>
            <div class="step-pill" onclick="goToStep(2)" id="pill-2">2. विषय (Subjects)</div>
            <div class="step-pill" onclick="goToStep(3)" id="pill-3">3. Marks निवडा</div>
            <div class="step-pill" onclick="goToStep(4)" id="pill-4">4. प्रश्न सेटिंग</div>
            <div class="step-pill" onclick="goToStep(5)" id="pill-5">5. Date & Publish</div>
        </div>

        <form method="POST" id="examWizardForm">
            <input type="hidden" name="action" id="wizardAction" value="create_exam">
            <input type="hidden" name="exam_id" id="wizardExamId" value="0">
            <input type="hidden" name="category_slug" id="wizardCategorySlug" value="">

            <!-- STEP 1: SELECT EXAM -->
            <div class="wizard-step active" id="step-1">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <label style="font-weight:700;">१. परीक्षा निवडा (Select Exam) *</label>
                    <button type="button" onclick="openManageModal()" style="font-size:12px; color:#4F46E5; background:none; border:none; cursor:pointer; font-weight:600;">+ नवीन परीक्षा जोडा</button>
                </div>
                <div class="choice-grid" id="examChoiceGrid">
                    <?php foreach ($examTypes as $et): ?>
                        <div class="choice-card" data-slug="<?php echo htmlspecialchars($et['slug']); ?>" onclick="selectExamType('<?php echo htmlspecialchars($et['slug']); ?>')">
                            <div style="font-size:13px; font-weight:700; color:<?php echo htmlspecialchars($et['color']); ?>;"><?php echo htmlspecialchars($et['badge']); ?></div>
                            <div style="font-size:14px; font-weight:700; margin:4px 0;"><?php echo htmlspecialchars($et['name']); ?></div>
                            <div style="font-size:11.5px; color:#64748b;"><?php echo htmlspecialchars($et['description']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-group">
                    <label>इयत्ता / वर्ग (Class)</label>
                    <select name="class_id" id="wizardClassId" class="form-control">
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo ($c['class_id'] == 38 || strpos($c['class_name'], 'Scholarship') !== false) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- STEP 2: SELECT SUBJECTS -->
            <div class="wizard-step" id="step-2">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <label style="font-weight:700;">२. परीक्षेचे विषय निवडा (Select Subjects) *</label>
                    <button type="button" onclick="openManageModal()" style="font-size:12px; color:#4F46E5; background:none; border:none; cursor:pointer; font-weight:600;">+ विषय Add/Edit करा</button>
                </div>
                <div id="dynamicSubjectsContainer" class="choice-grid"></div>
                <div class="form-group" style="margin-top:12px;">
                    <label>अभ्यासक्रमाचे धडे (Optional Chapter Filter for MCQs)</label>
                    <div style="max-height:140px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:8px; padding:10px; background:#f8fafc;">
                        <?php foreach ($chapters as $ch): ?>
                            <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; font-weight:400; margin-bottom:5px;">
                                <input type="checkbox" name="chapter_ids[]" value="<?php echo $ch['chapter_id']; ?>" class="wizard-ch-cb">
                                <strong>[<?php echo htmlspecialchars($ch['subject_name']); ?>]</strong> <?php echo htmlspecialchars($ch['chapter_name']); ?> (<?php echo $ch['mcq_count']; ?> Qs)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- STEP 3: SELECT EXAM MARKS -->
            <div class="wizard-step" id="step-3">
                <label style="font-weight:700; display:block; margin-bottom:10px;">३. परीक्षेचे एकूण गुण निवडा (Select Exam Marks) *</label>
                <div class="marks-grid">
                    <div class="marks-card" onclick="selectMarksPreset(30, this)">30<br><span style="font-size:11px;font-weight:500;">Marks</span></div>
                    <div class="marks-card" onclick="selectMarksPreset(50, this)">50<br><span style="font-size:11px;font-weight:500;">Marks</span></div>
                    <div class="marks-card" onclick="selectMarksPreset(60, this)">60<br><span style="font-size:11px;font-weight:500;">Marks</span></div>
                    <div class="marks-card selected" onclick="selectMarksPreset(100, this)">100<br><span style="font-size:11px;font-weight:500;">Marks</span></div>
                    <div class="marks-card" onclick="selectMarksPreset('custom', this)">Custom<br><span style="font-size:11px;font-weight:500;">Marks</span></div>
                </div>
                <div class="form-group">
                    <label>एकूण गुण (Total Exam Marks)</label>
                    <input type="number" step="1" min="5" max="500" name="total_marks" id="wizardTotalMarks" class="form-control" value="100" oninput="recalcQuestionConfig()">
                </div>
            </div>

            <!-- STEP 4: QUESTION CONFIGURATION -->
            <div class="wizard-step" id="step-4">
                <div class="form-row">
                    <div class="form-group">
                        <label>एकूण प्रश्न (Total Questions)</label>
                        <input type="number" min="1" max="200" name="question_limit" id="wizardQuestionLimit" class="form-control" value="50" oninput="recalcFromQuestions()">
                    </div>
                    <div class="form-group">
                        <label>प्रत्येक प्रश्नाला गुण (Marks per Question)</label>
                        <input type="number" step="0.25" min="0.25" name="positive_marks" id="wizardPosMarks" class="form-control" value="2.00" oninput="recalcTotalCheck()">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Negative Marking</label>
                        <div style="display:flex; gap:12px; align-items:center; padding-top:6px;">
                            <label style="display:flex; align-items:center; gap:6px; font-weight:500; margin:0;"><input type="checkbox" name="negative_marking_enabled" id="wizardNegEnabled" value="1" onchange="toggleNegInput()"> Negative Marking ON</label>
                            <input type="number" step="0.25" min="0" name="negative_marks" id="wizardNegMarks" class="form-control" value="0.25" style="width:100px; display:none;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Difficulty Level</label>
                        <select name="difficulty_level" id="wizardDifficulty" class="form-control">
                            <option value="mixed">Mixed (सर्व प्रकारचे)</option>
                            <option value="easy">Easy (सोपे)</option>
                            <option value="medium">Medium (मध्यम)</option>
                            <option value="hard">Hard (कठीण)</option>
                        </select>
                    </div>
                </div>

                <div id="marksMatchBanner" style="padding:10px 14px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:14px; background:#dcfce7; color:#166534;">
                    ✅ जुळले: 50 प्रश्न × 2 गुण = 100 गुण
                </div>

                <label style="font-weight:700; display:block; margin-bottom:8px;">Subject-wise Question Distribution (विषयनिहाय प्रश्न संख्या)</label>
                <div id="subjectDistributionList"></div>
            </div>

            <!-- STEP 5: DATE, TIME & PUBLISH -->
            <div class="wizard-step" id="step-5">
                <div class="form-group">
                    <label>परीक्षेचे नाव (Exam Title) *</label>
                    <input type="text" name="title" id="wizardTitle" class="form-control" required placeholder="उदा. नवोदय महा-सराव परीक्षा - १">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Exam Date</label>
                        <input type="date" name="exam_date" id="wizardExamDate" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Exam Duration (Minutes)</label>
                        <input type="number" min="5" name="duration_minutes" id="wizardDuration" class="form-control" value="60">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time_only" id="wizardStartTime" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time_only" id="wizardEndTime" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Exam Status</label>
                    <select name="exam_status" id="wizardStatus" class="form-control">
                        <option value="scheduled">📅 Scheduled (वेळेवर सुरू होईल)</option>
                        <option value="live">🔴 Live Now (लगेच लाइव्ह करा)</option>
                        <option value="draft">📝 Draft (मसुदा म्हणून सेव्ह करा)</option>
                        <option value="completed">✅ Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Exam Instructions</label>
                    <textarea name="instructions" id="wizardInstructions" class="form-control" rows="3" placeholder="विद्यार्थ्यांसाठी सूचना..."></textarea>
                </div>
            </div>

            <div class="wizard-footer">
                <button type="button" class="btn-secondary" id="btnPrevStep" onclick="prevStep()">⬅ मागे</button>
                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn-primary" id="btnNextStep" onclick="nextStep()">पुढे ➔</button>
                    <button type="submit" class="btn-create" id="btnSubmitWizard" style="display:none;">🚀 Create Live Exam</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- VIEW EXAM MODAL -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width:620px;">
        <div class="modal-header">
            <h2 id="viewModalTitle">👁️ Exam Details</h2>
            <button onclick="closeModal('viewModal')" class="close-btn">&times;</button>
        </div>
        <div id="viewModalBody" style="font-size:14px; line-height:1.7;"></div>
    </div>
</div>

<!-- STUDENT LIVE EXAM DATABASE MODAL -->
<div id="studentsModal" class="modal">
    <div class="modal-content" style="max-width:920px;">
        <div class="modal-header">
            <h2 id="studentsModalTitle">👥 Live Exam Student Database</h2>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="button" onclick="exportStudentsCSV()" class="btn-primary" style="padding:7px 14px;font-size:12px;">📥 Export CSV</button>
                <button onclick="closeModal('studentsModal')" class="close-btn">&times;</button>
            </div>
        </div>
        <div id="studentsModalBody" style="overflow-x:auto; max-height:460px;"></div>
    </div>
</div>

<!-- MANAGE EXAMS & SUBJECTS MODAL -->
<div id="manageModal" class="modal">
    <div class="modal-content" style="max-width:780px;">
        <div class="modal-header">
            <h2>⚙️ परीक्षा व विषय व्यवस्थापन (Dynamic Admin)</h2>
            <button onclick="closeModal('manageModal')" class="close-btn">&times;</button>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
            <div>
                <h3 style="font-size:15px; margin-bottom:10px;">➕ नवीन परीक्षा प्रकार जोडा</h3>
                <form method="POST" style="background:#f8fafc; padding:14px; border-radius:12px; border:1px solid #e2e8f0; margin-bottom:14px;">
                    <input type="hidden" name="action" value="save_exam_type">
                    <div class="form-group"><label>परीक्षेचे नाव *</label><input type="text" name="type_name" class="form-control" required placeholder="उदा. MPSC / NTS Exam"></div>
                    <div class="form-row">
                        <div class="form-group"><label>Badge</label><input type="text" name="type_badge" class="form-control" value="📘 Exam"></div>
                        <div class="form-group"><label>Color</label><input type="color" name="type_color" class="form-control" value="#4F46E5" style="height:40px;"></div>
                    </div>
                    <div class="form-group"><label>वर्णन</label><input type="text" name="type_desc" class="form-control" placeholder="थोडक्यात माहिती"></div>
                    <button type="submit" class="btn-primary" style="width:100%;">Save Exam Type</button>
                </form>

                <div style="max-height:220px; overflow-y:auto;">
                    <?php foreach ($examTypes as $et): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; border-bottom:1px solid #e2e8f0; font-size:13px;">
                            <span><strong><?php echo htmlspecialchars($et['name']); ?></strong> (<?php echo count($et['subjects']); ?> विषय)</span>
                            <form method="POST" onsubmit="return confirm('हा परीक्षा प्रकार डिलीट करायचा का?');">
                                <input type="hidden" name="action" value="delete_exam_type">
                                <input type="hidden" name="exam_type_id" value="<?php echo $et['exam_type_id']; ?>">
                                <button type="submit" class="btn-action btn-delete">🗑️</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <h3 style="font-size:15px; margin-bottom:10px;">📚 परीक्षेचे विषय (Subjects) Manage करा</h3>
                <form method="POST" style="background:#f8fafc; padding:14px; border-radius:12px; border:1px solid #e2e8f0; margin-bottom:14px;">
                    <input type="hidden" name="action" value="save_exam_subject">
                    <div class="form-group">
                        <label>परीक्षा निवडा *</label>
                        <select name="exam_type_id" id="manageSubjectExamSelect" class="form-control" onchange="renderManageSubjectsList()">
                            <?php foreach ($examTypes as $et): ?>
                                <option value="<?php echo $et['exam_type_id']; ?>"><?php echo htmlspecialchars($et['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>नवीन विषयाचे नाव *</label><input type="text" name="subject_name" class="form-control" required placeholder="उदा. Logical Reasoning"></div>
                    <div class="form-group">
                        <label>Link to MCQ Bank Subject (Optional)</label>
                        <select name="linked_subject_id" class="form-control">
                            <option value="0">-- Auto-match by name --</option>
                            <?php foreach ($allDbSubjects as $dbs): ?>
                                <option value="<?php echo $dbs['subject_id']; ?>">
                                    <?php echo htmlspecialchars($dbs['subject_name']); ?> (<?php echo intval($dbs['mcq_count']); ?> MCQs)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">Add Subject to Exam</button>
                </form>

                <div id="manageSubjectsList" style="max-height:220px; overflow-y:auto;"></div>
            </div>
        </div>
    </div>
</div>

<script>
const EXAM_TYPES = <?php echo json_encode($examTypes, JSON_UNESCAPED_UNICODE); ?>;
const DB_SUBJECTS = <?php echo json_encode($allDbSubjects, JSON_UNESCAPED_UNICODE); ?>;
let currentStep = 1;
let selectedSlug = EXAM_TYPES.length ? EXAM_TYPES[0].slug : '';
let selectedSubjects = [];
let customDist = {};

function openCreateWizard() {
    document.getElementById('wizardModalTitle').textContent = '➕ नवीन Dynamic Live Exam तयार करा';
    document.getElementById('wizardAction').value = 'create_exam';
    document.getElementById('wizardExamId').value = '0';
    document.getElementById('examWizardForm').reset();
    selectedSubjects = [];
    customDist = {};
    if (EXAM_TYPES.length) selectExamType(EXAM_TYPES[0].slug);
    goToStep(1);
    document.getElementById('createModal').style.display = 'block';
}

function editExamWizard(ex) {
    document.getElementById('wizardModalTitle').textContent = '✏️ Edit Live Exam: ' + ex.title;
    document.getElementById('wizardAction').value = 'update_exam';
    document.getElementById('wizardExamId').value = ex.exam_id;
    document.getElementById('wizardTitle').value = ex.title || '';
    document.getElementById('wizardClassId').value = ex.class_id || 38;
    document.getElementById('wizardDuration').value = ex.duration_minutes || 60;
    document.getElementById('wizardTotalMarks').value = parseFloat(ex.total_marks || 100);
    document.getElementById('wizardQuestionLimit').value = parseInt(ex.total_questions || 50);
    document.getElementById('wizardPosMarks').value = parseFloat(ex.positive_marks || 2);
    document.getElementById('wizardDifficulty').value = ex.difficulty_level || 'mixed';
    document.getElementById('wizardStatus').value = ex.exam_status || (ex.is_live == 1 ? 'scheduled' : 'draft');
    document.getElementById('wizardInstructions').value = ex.instructions || '';

    const negOn = parseInt(ex.negative_marking_enabled || 0) === 1 || parseFloat(ex.negative_marks || 0) > 0;
    document.getElementById('wizardNegEnabled').checked = negOn;
    document.getElementById('wizardNegMarks').value = parseFloat(ex.negative_marks || 0.25);
    toggleNegInput();

    if (ex.start_time) {
        const parts = ex.start_time.split(' ');
        document.getElementById('wizardExamDate').value = parts[0] || '';
        document.getElementById('wizardStartTime').value = (parts[1] || '').slice(0, 5);
    }
    if (ex.end_time) {
        const parts = ex.end_time.split(' ');
        document.getElementById('wizardEndTime').value = (parts[1] || '').slice(0, 5);
    }

    try { selectedSubjects = JSON.parse(ex.selected_subjects || '[]') || []; } catch(e) { selectedSubjects = []; }
    try { customDist = JSON.parse(ex.subject_distribution || '{}') || {}; } catch(e) { customDist = {}; }

    selectExamType(ex.category_slug || (EXAM_TYPES[0]?.slug || ''));
    goToStep(1);
    document.getElementById('createModal').style.display = 'block';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function selectExamType(slug) {
    selectedSlug = slug;
    document.getElementById('wizardCategorySlug').value = slug;
    document.querySelectorAll('#examChoiceGrid .choice-card').forEach(c => {
        c.classList.toggle('selected', c.dataset.slug === slug);
    });
    renderSubjectsForExam();
}

function renderSubjectsForExam() {
    const et = EXAM_TYPES.find(x => x.slug === selectedSlug) || EXAM_TYPES[0];
    const container = document.getElementById('dynamicSubjectsContainer');
    if (!et || !et.subjects.length) {
        container.innerHTML = '<div style="color:#64748b;font-size:13px;">या परीक्षेसाठी अद्याप विषय जोडलेले नाहीत. कृपया "+ विषय Add/Edit करा" वर क्लिक करा.</div>';
        return;
    }
    container.innerHTML = et.subjects.map(s => {
        const checked = selectedSubjects.includes(s.subject_name) ? 'checked' : '';
        const avail = parseInt(s.available_mcqs || 0);
        const badgeColor = avail > 0 ? '#15803d;background:#dcfce7' : '#b45309;background:#fef3c7';
        return `
            <label class="choice-card ${checked ? 'selected' : ''}" style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0;">
                <span style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="selected_subjects[]" value="${escapeHtml(s.subject_name)}" ${checked} onchange="onSubjectToggle(this)">
                    <span style="font-size:13.5px;font-weight:600;">${escapeHtml(s.subject_name)}</span>
                </span>
                <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:50px;color:${badgeColor};">${avail} MCQs</span>
            </label>
        `;
    }).join('');
    renderSubjectDistribution();
}

function onSubjectToggle(cb) {
    cb.closest('.choice-card').classList.toggle('selected', cb.checked);
    selectedSubjects = Array.from(document.querySelectorAll('input[name="selected_subjects[]"]:checked')).map(x => x.value);
    renderSubjectDistribution();
}

function selectMarksPreset(val, el) {
    document.querySelectorAll('.marks-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    const input = document.getElementById('wizardTotalMarks');
    if (val !== 'custom') {
        input.value = val;
        recalcQuestionConfig();
    } else {
        input.focus();
    }
}

function recalcQuestionConfig() {
    const totalMarks = parseFloat(document.getElementById('wizardTotalMarks').value) || 0;
    const pos = parseFloat(document.getElementById('wizardPosMarks').value) || 1;
    if (totalMarks > 0 && pos > 0) {
        document.getElementById('wizardQuestionLimit').value = Math.max(1, Math.round(totalMarks / pos));
    }
    recalcTotalCheck();
    renderSubjectDistribution();
}

function recalcFromQuestions() {
    const totalMarks = parseFloat(document.getElementById('wizardTotalMarks').value) || 0;
    const q = parseInt(document.getElementById('wizardQuestionLimit').value) || 1;
    if (totalMarks > 0 && q > 0) {
        document.getElementById('wizardPosMarks').value = (totalMarks / q).toFixed(2);
    }
    recalcTotalCheck();
    renderSubjectDistribution();
}

function recalcTotalCheck() {
    const totalMarks = parseFloat(document.getElementById('wizardTotalMarks').value) || 0;
    const q = parseInt(document.getElementById('wizardQuestionLimit').value) || 0;
    const pos = parseFloat(document.getElementById('wizardPosMarks').value) || 0;
    const calc = +(q * pos).toFixed(2);
    const banner = document.getElementById('marksMatchBanner');
    if (Math.abs(calc - totalMarks) < 0.01) {
        banner.style.background = '#dcfce7'; banner.style.color = '#166534';
        banner.textContent = `✅ जुळले: ${q} प्रश्न × ${pos} गुण = ${calc} गुण`;
    } else {
        banner.style.background = '#fef3c7'; banner.style.color = '#92400e';
        banner.textContent = `⚠️ टीप: ${q} प्रश्न × ${pos} गुण = ${calc} (निवडलेले एकूण गुण: ${totalMarks})`;
    }
}

function toggleNegInput() {
    document.getElementById('wizardNegMarks').style.display = document.getElementById('wizardNegEnabled').checked ? 'block' : 'none';
}

function renderSubjectDistribution() {
    const list = document.getElementById('subjectDistributionList');
    const qLimit = parseInt(document.getElementById('wizardQuestionLimit').value) || 0;
    if (!selectedSubjects.length) {
        list.innerHTML = '<div style="font-size:12.5px;color:#64748b;">Step 2 मध्ये विषय निवडल्यावर येथे विषयनिहाय प्रश्न संख्या सेट करता येईल.</div>';
        return;
    }
    const et = EXAM_TYPES.find(x => x.slug === selectedSlug) || EXAM_TYPES[0];
    const perSub = Math.floor(qLimit / selectedSubjects.length);
    let rem = qLimit - (perSub * selectedSubjects.length);
    list.innerHTML = selectedSubjects.map((sub, idx) => {
        const defVal = customDist[sub] !== undefined ? customDist[sub] : (perSub + (idx < rem ? 1 : 0));
        const subObj = et?.subjects?.find(x => x.subject_name === sub) || {};
        const avail = parseInt(subObj.available_mcqs || 0);
        const missing = Math.max(0, defVal - avail);
        const genCount = Math.min(25, missing);
        return `
            <div class="dist-row" style="flex-wrap:wrap;gap:8px;">
                <div>
                    <div style="font-size:13px;font-weight:600;">${escapeHtml(sub)}</div>
                    <div style="font-size:11.5px;color:${missing > 0 ? '#dc2626' : '#15803d'};font-weight:600;">
                        Available: ${avail} MCQs ${missing > 0 ? `(⚠️ ${missing} प्रश्न कमी आहेत)` : '✅ पुरेसे आहेत'}
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    ${missing > 0 ? `<button type="button" onclick="autoGenerateMissingMCQs('${escapeHtml(sub)}', ${subObj.id || 0}, ${subObj.linked_subject_id || 0}, ${genCount}, this)" class="btn-action" style="background:#7C3AED;color:white;font-size:11.5px;">✨ AI ने ${genCount} प्रश्न तयार करा</button>` : ''}
                    <input type="number" min="0" name="subject_dist[${escapeHtml(sub)}]" value="${defVal}" class="form-control" style="width:85px;padding:6px 10px;" onchange="customDist['${escapeHtml(sub)}']=parseInt(this.value||0);renderSubjectDistribution();">
                    <span style="font-size:12px;color:#64748b;">प्रश्न</span>
                </div>
            </div>
        `;
    }).join('');
}

async function autoGenerateMissingMCQs(subName, subMapId, linkedSubId, count, btn) {
    const origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ AI तयार करत आहे...';
    try {
        const fd = new FormData();
        fd.append('action', 'ajax_generate_missing_mcqs');
        fd.append('subject_name', subName);
        fd.append('subject_map_id', subMapId);
        fd.append('linked_subject_id', linkedSubId);
        fd.append('category_slug', selectedSlug);
        fd.append('class_id', document.getElementById('wizardClassId').value);
        fd.append('difficulty_level', document.getElementById('wizardDifficulty').value);
        fd.append('count', count);
        const res = await fetch('', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.status === 'success') {
            const et = EXAM_TYPES.find(x => x.slug === selectedSlug);
            const subObj = et?.subjects?.find(x => x.subject_name === subName);
            if (subObj) {
                subObj.available_mcqs = json.new_mcq_count;
                subObj.linked_subject_id = json.subject_id;
            }
            renderSubjectsForExam();
            renderSubjectDistribution();
            alert(`✅ ${json.added} नवीन MCQs यशस्वीरित्या तयार व सेव्ह केले!`);
        } else {
            alert('❌ AI Error: ' + (json.message || 'Failed'));
            btn.disabled = false;
            btn.textContent = origText;
        }
    } catch (e) {
        alert('❌ Network error while generating MCQs.');
        btn.disabled = false;
        btn.textContent = origText;
    }
}

function goToStep(step) {
    if (step === 3 && selectedSubjects.length === 0) {
        selectedSubjects = Array.from(document.querySelectorAll('input[name="selected_subjects[]"]:checked')).map(x => x.value);
    }
    currentStep = step;
    document.querySelectorAll('.wizard-step').forEach((el, i) => el.classList.toggle('active', i + 1 === step));
    document.querySelectorAll('.step-pill').forEach((el, i) => {
        el.classList.remove('active', 'done');
        if (i + 1 === step) el.classList.add('active');
        else if (i + 1 < step) el.classList.add('done');
    });
    document.getElementById('btnPrevStep').style.visibility = step === 1 ? 'hidden' : 'visible';
    document.getElementById('btnNextStep').style.display = step === 5 ? 'none' : 'inline-block';
    document.getElementById('btnSubmitWizard').style.display = step === 5 ? 'inline-flex' : 'none';
    if (step === 4) { recalcTotalCheck(); renderSubjectDistribution(); }
}

function nextStep() {
    if (currentStep === 2) {
        selectedSubjects = Array.from(document.querySelectorAll('input[name="selected_subjects[]"]:checked')).map(x => x.value);
        if (!selectedSubjects.length) {
            alert('कृपया किमान एक विषय (Subject) निवडा.');
            return;
        }
    }
    if (currentStep < 5) goToStep(currentStep + 1);
}
function prevStep() { if (currentStep > 1) goToStep(currentStep - 1); }

function viewExamDetails(ex) {
    const subs = JSON.parse(ex.selected_subjects || '[]') || [];
    const dist = JSON.parse(ex.subject_distribution || '{}') || {};
    document.getElementById('viewModalTitle').textContent = '👁️ ' + ex.title;
    document.getElementById('viewModalBody').innerHTML = `
        <p><strong>Category:</strong> ${escapeHtml(ex.category_slug || '-')}</p>
        <p><strong>Total Marks:</strong> ${ex.total_marks || 100} | <strong>Questions:</strong> ${ex.total_questions}</p>
        <p><strong>Duration:</strong> ${ex.duration_minutes} mins | <strong>Difficulty:</strong> ${ex.difficulty_level || 'mixed'}</p>
        <p><strong>Schedule:</strong> ${ex.start_time || '-'} to ${ex.end_time || '-'}</p>
        <p><strong>Subjects:</strong> ${subs.join(', ') || '-'}</p>
        <div style="margin-top:10px;background:#f8fafc;padding:12px;border-radius:10px;">
            <strong>Subject-wise Distribution:</strong>
            <ul style="margin-left:18px;margin-top:6px;">
                ${Object.entries(dist).map(([k, v]) => `<li>${escapeHtml(k)}: ${v} प्रश्न</li>`).join('') || '<li>Auto</li>'}
            </ul>
        </div>
        <p style="margin-top:10px;"><strong>Instructions:</strong> ${escapeHtml(ex.instructions || 'None')}</p>
    `;
    document.getElementById('viewModal').style.display = 'block';
}

function openManageModal() {
    renderManageSubjectsList();
    document.getElementById('manageModal').style.display = 'block';
}

function renderManageSubjectsList() {
    const tid = parseInt(document.getElementById('manageSubjectExamSelect').value);
    const et = EXAM_TYPES.find(x => parseInt(x.exam_type_id) === tid);
    const container = document.getElementById('manageSubjectsList');
    if (!et || !et.subjects.length) {
        container.innerHTML = '<div style="color:#64748b;font-size:13px;">कोणतेही विषय नाहीत.</div>';
        return;
    }
    container.innerHTML = et.subjects.map(s => {
        const opts = ['<option value="0">-- Auto-match --</option>'].concat(
            DB_SUBJECTS.map(d => `<option value="${d.subject_id}" ${parseInt(s.linked_subject_id||0)===parseInt(d.subject_id)?'selected':''}>${escapeHtml(d.subject_name)} (${d.mcq_count})</option>`)
        ).join('');
        return `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-bottom:1px solid #e2e8f0;font-size:13px;gap:6px;">
            <form method="POST" style="display:flex;gap:6px;flex:1;align-items:center;">
                <input type="hidden" name="action" value="save_exam_subject">
                <input type="hidden" name="exam_type_id" value="${tid}">
                <input type="hidden" name="subject_map_id" value="${s.id}">
                <input type="text" name="subject_name" value="${escapeHtml(s.subject_name)}" class="form-control" style="padding:5px 8px;font-size:12px;flex:1;">
                <select name="linked_subject_id" class="form-control" style="padding:5px 6px;font-size:11.5px;width:140px;">${opts}</select>
                <button type="submit" class="btn-action">💾</button>
            </form>
            <form method="POST" onsubmit="return confirm('विषय हटवायचा का?');">
                <input type="hidden" name="action" value="delete_exam_subject">
                <input type="hidden" name="subject_map_id" value="${s.id}">
                <button type="submit" class="btn-action btn-delete">🗑️</button>
            </form>
        </div>
    `}).join('');
}

function escapeHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let currentStudentsData = [];
let currentStudentsExamTitle = '';

async function openStudentsModal(examId, title) {
    currentStudentsExamTitle = title || 'Live_Exam';
    document.getElementById('studentsModalTitle').textContent = '👥 Students Database: ' + currentStudentsExamTitle;
    const body = document.getElementById('studentsModalBody');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:#64748b;">लोड होत आहे...</div>';
    document.getElementById('studentsModal').style.display = 'block';
    try {
        const res = await fetch('?ajax_students=' + examId);
        const json = await res.json();
        currentStudentsData = json.data || [];
        if (!currentStudentsData.length) {
            body.innerHTML = '<div style="padding:30px;text-align:center;color:#64748b;">अद्याप कोणत्याही विद्यार्थ्याने ही परीक्षा दिलेली नाही.</div>';
            return;
        }
        body.innerHTML = `
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left;">
                        <th style="padding:10px;">Rank</th>
                        <th style="padding:10px;">Student</th>
                        <th style="padding:10px;">Contact</th>
                        <th style="padding:10px;">Score</th>
                        <th style="padding:10px;">C / W / S</th>
                        <th style="padding:10px;">Accuracy</th>
                        <th style="padding:10px;">Time</th>
                        <th style="padding:10px;">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    ${currentStudentsData.map(r => `
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px;font-weight:700;color:#4F46E5;">#${r.rank}</td>
                            <td style="padding:10px;font-weight:600;">${escapeHtml(r.student_name)}</td>
                            <td style="padding:10px;color:#64748b;font-size:12px;">${escapeHtml(r.phone || r.email || '-')}</td>
                            <td style="padding:10px;font-weight:700;color:#15803d;">${r.net_score} / ${r.max_possible_score}</td>
                            <td style="padding:10px;">✅${r.correct_count} ❌${r.wrong_count} ⏭️${r.unattempted_count}</td>
                            <td style="padding:10px;">${r.accuracy_percentage}%</td>
                            <td style="padding:10px;">${Math.floor(r.time_spent_seconds/60)}m ${r.time_spent_seconds%60}s</td>
                            <td style="padding:10px;font-size:12px;color:#64748b;">${escapeHtml(r.created_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } catch (err) {
        body.innerHTML = '<div style="padding:20px;color:#dc2626;">डेटा लोड करताना त्रुटी आली.</div>';
    }
}

function exportStudentsCSV() {
    if (!currentStudentsData.length) return;
    const headers = ['Rank', 'Student Name', 'Phone', 'Email', 'Score', 'Max Marks', 'Correct', 'Wrong', 'Skipped', 'Accuracy %', 'Time (sec)', 'Submitted At'];
    const lines = [headers.join(',')].concat(currentStudentsData.map(r => [
        r.rank,
        `"${String(r.student_name).replace(/"/g, '""')}"`,
        `"${String(r.phone)}"`,
        `"${String(r.email)}"`,
        r.net_score, r.max_possible_score, r.correct_count, r.wrong_count, r.unattempted_count,
        r.accuracy_percentage, r.time_spent_seconds, `"${r.created_at}"`
    ].join(',')));
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = currentStudentsExamTitle.replace(/[^a-z0-9]/gi, '_') + '_students.csv';
    a.click();
}

window.onclick = function(e) {
    ['createModal', 'viewModal', 'manageModal', 'studentsModal'].forEach(id => {
        const m = document.getElementById(id);
        if (e.target === m) m.style.display = 'none';
    });
};
</script>
</body>
</html>
