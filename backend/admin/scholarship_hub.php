<?php
/**
 * Scholarship & Competitive Exam Content Studio (Veeru Admin)
 * AI Question Generator, CSV Bulk Upload & Question Bank Organizer
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

// ─────────────────────────────────────────────────────────────────────────────
// 1. SELF-HEALING DATABASE MIGRATION
// ─────────────────────────────────────────────────────────────────────────────
try {
    $mcqCols = $pdo->query("SHOW COLUMNS FROM `mcqs`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category_slug', $mcqCols)) {
        $pdo->exec("ALTER TABLE `mcqs` ADD COLUMN `category_slug` VARCHAR(50) DEFAULT 'scholarship', ADD INDEX (`category_slug`)");
    }
    if (!in_array('image_url', $mcqCols)) {
        $pdo->exec("ALTER TABLE `mcqs` ADD COLUMN `image_url` VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {}

// ─────────────────────────────────────────────────────────────────────────────
// 2. EXAM CATEGORIES MASTER
// ─────────────────────────────────────────────────────────────────────────────
$CATEGORIES = [
    'scholarship' => [
        'name' => 'महाराष्ट्र राज्य शिष्यवृत्ती (5th & 8th)',
        'badge' => '📜 राज्य शिष्यवृत्ती',
        'color' => '#4F46E5',
        'bg' => '#EEF2FF',
        'subjects' => ['मराठी / प्रथम भाषा', 'गणित (Mathematics)', 'तृतीय भाषा (English)', 'बुद्धिमत्ता चाचणी (Mental Ability)']
    ],
    'navodaya' => [
        'name' => 'जवाहर नवोदय विद्यालय (JNVST)',
        'badge' => '🏛️ नवोदय विद्यालय',
        'color' => '#0284C7',
        'bg' => '#E0F2FE',
        'subjects' => ['मानसिक क्षमता चाचणी (Mental Ability)', 'अंकगणित (Arithmetic)', 'भाषा चाचणी (Language / Marathi / English)']
    ],
    'sainik' => [
        'name' => 'सैनिक शाळा प्रवेश परीक्षा (AISSEE)',
        'badge' => '🎖️ सैनिक शाळा',
        'color' => '#15803D',
        'bg' => '#DCFCE7',
        'subjects' => ['गणित (Mathematics - 150 गुण)', 'बुद्धिमत्ता चाचणी (Intelligence)', 'इंग्रजी व्याकरण (English Grammar)', 'सामान्य ज्ञान (General Knowledge)']
    ],
    'olympiad' => [
        'name' => 'ऑलिम्पियाड स्पर्धा परीक्षा (SOF/Homi Bhabha)',
        'badge' => '🏆 ऑलिम्पियाड',
        'color' => '#D97706',
        'bg' => '#FEF3C7',
        'subjects' => ['IMO - Mathematics', 'NSO - Science', 'IEO - English Grammar', 'Logical Reasoning', 'Achievers Section (HOTS)']
    ],
    'mts' => [
        'name' => 'MTS / NMMS प्रज्ञाशोध परीक्षा',
        'badge' => '💡 MTS / NMMS',
        'color' => '#7C3AED',
        'bg' => '#F3E8FF',
        'subjects' => ['MAT - मानसिक क्षमता चाचणी', 'SAT - विज्ञान (Science)', 'SAT - सामाजिक शास्त्रे', 'SAT - गणित (Mathematics)']
    ]
];

// Pre-packaged high-yield topic recommendations for quick AI generation
$QUICK_TOPICS = [
    'Logical Reasoning' => ['अक्षरमाला (Alphabet Series)', 'संख्यामाला (Number Series)', 'सांकेतिक भाषा (Coding-Decoding)', 'नातेसंबंध (Blood Relations)', 'दिशा ज्ञान (Directions)', 'विजोड पद (Odd-Man Out)', 'समसंबंध (Analogy)', 'वेन आकृत्या (Venn Diagrams)', 'दिनदर्शिका व घड्याळ (Calendar & Clock)'],
    'English Grammar' => ['Tenses & Correct Verb Forms', 'Prepositions & Articles', 'Active & Passive Voice', 'Direct & Indirect Speech', 'Synonyms & Antonyms', 'One Word Substitution', 'Spotting Common Errors', 'Jumbled Words & Sentences', 'Idioms & Proverbs'],
    'Mathematics' => ['अपूर्णांक (Fractions & Decimals)', 'लसावि व मसावि (LCM & HCF)', 'शेकडेवारी (Percentages)', 'नफा व तोटा (Profit & Loss)', 'सरळव्याज (Simple Interest)', 'गुणोत्तर व प्रमाण (Ratio & Proportion)', 'क्षेत्रफळ व परिमिती (Perimeter & Area)', 'काळ, काम व वेग (Speed & Distance)']
];

// ─────────────────────────────────────────────────────────────────────────────
// 3. AJAX HANDLER: AI MCQ GENERATION
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'ai_generate_mcqs') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $category_slug = trim($_POST['category_slug'] ?? 'scholarship');
    $subject = trim($_POST['subject'] ?? 'Mental Ability');
    $topic = trim($_POST['topic'] ?? 'Coding-Decoding');
    $target_class = intval($_POST['target_class'] ?? 5);
    $difficulty = trim($_POST['difficulty'] ?? 'medium');
    $language = trim($_POST['language'] ?? 'Marathi');
    $count = min(20, max(3, intval($_POST['count'] ?? 5)));
    
    $cat_info = $CATEGORIES[$category_slug] ?? $CATEGORIES['scholarship'];
    
    $prompt = "You are an elite competitive exam paper setter specializing in Indian entrance & scholarship exams:
Exam: {$cat_info['name']}
Target Grade: Class {$target_class}
Subject: {$subject}
Topic: {$topic}
Difficulty: {$difficulty}
Language: {$language}

Generate exactly {$count} multiple-choice questions (MCQs) for this topic.

RULES:
1. Each question must have exactly ONE unequivocally correct answer.
2. The other 3 options must be plausible distractors that test common student misconceptions.
3. Provide a clear, step-by-step explanation teaching the underlying rule, logic, or formula.
4. If Language is Marathi, write natural, clear Marathi with English mathematical terms in brackets where helpful.
5. If Subject is English Grammar, the question and options must be in English, while the explanation can be bilingual.
6. Return purely valid JSON matching this schema:
{
  \"questions\": [
    {
      \"question\": \"Question text here\",
      \"option_a\": \"Option A text\",
      \"option_b\": \"Option B text\",
      \"option_c\": \"Option C text\",
      \"option_d\": \"Option D text\",
      \"correct_answer\": \"a\",
      \"explanation\": \"Step-by-step solution and reason\",
      \"difficulty\": \"{$difficulty}\"
    }
  ]
}";

    try {
        if (!function_exists('callGeminiAPI')) {
            throw new Exception("Gemini AI API helper function 'callGeminiAPI' not loaded.");
        }
        
        $aiRaw = callGeminiAPI($prompt, [
            'temperature' => 0.4,
            'maxOutputTokens' => 4096,
            'responseMimeType' => 'application/json'
        ]);
        
        $parsed = json_decode($aiRaw, true);
        if (!isset($parsed['questions']) || !is_array($parsed['questions'])) {
            // Fallback: search for json block if markdown wrapped
            if (preg_match('/\{[\s\S]*\}/', $aiRaw, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
        }
        
        if (!isset($parsed['questions']) || empty($parsed['questions'])) {
            throw new Exception("AI returned invalid JSON format.");
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $parsed['questions'],
            'meta' => [
                'category_slug' => $category_slug,
                'subject' => $subject,
                'topic' => $topic,
                'count' => count($parsed['questions'])
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. ACTION HANDLERS: SAVE AI MCQS, BULK CSV, DELETE
// ─────────────────────────────────────────────────────────────────────────────
$message = '';
$messageType = '';

// A. Save Approved AI MCQs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_approved_mcqs') {
    $chapter_id_input = trim($_POST['chapter_id'] ?? 'auto_create');
    $category_slug = trim($_POST['category_slug'] ?? 'scholarship');
    $subject_name = trim($_POST['generated_subject'] ?? 'Mental Ability');
    $topic_name = trim($_POST['generated_topic'] ?? 'General Competitive Topic');
    $target_class = intval($_POST['generated_grade'] ?? 5);
    $approved_questions = $_POST['questions'] ?? [];
    
    if (empty($approved_questions) || !is_array($approved_questions)) {
        $message = "कोणताही प्रश्न सेव्ह करण्यासाठी निवडलेला नाही.";
        $messageType = "error";
    } else {
        $savedCount = 0;
        try {
            $pdo->beginTransaction();

            $chapter_id = intval($chapter_id_input);
            // Auto-create chapter and subject if requested or if no chapter selected
            if ($chapter_id_input === 'auto_create' || $chapter_id <= 0) {
                $target_class_id = ($target_class == 8) ? 40 : 38;

                // Find or create subject
                $stmtSub = $pdo->prepare("SELECT subject_id FROM subjects WHERE class_id = ? AND (subject_name = ? OR subject_name LIKE ?) LIMIT 1");
                $firstWord = explode(' ', $subject_name)[0] ?? $subject_name;
                $stmtSub->execute([$target_class_id, $subject_name, "%$firstWord%"]);
                $sub_id = $stmtSub->fetchColumn();

                if (!$sub_id) {
                    $stmtCreateSub = $pdo->prepare("INSERT INTO subjects (subject_name, class_id) VALUES (?, ?)");
                    $stmtCreateSub->execute([$subject_name, $target_class_id]);
                    $sub_id = $pdo->lastInsertId();
                }

                // Find or create chapter
                $stmtCh = $pdo->prepare("SELECT chapter_id FROM chapters WHERE subject_id = ? AND chapter_name = ? LIMIT 1");
                $stmtCh->execute([$sub_id, $topic_name]);
                $chapter_id = $stmtCh->fetchColumn();

                if (!$chapter_id) {
                    $stmtCreateCh = $pdo->prepare("INSERT INTO chapters (chapter_name, subject_id) VALUES (?, ?)");
                    $stmtCreateCh->execute([$topic_name, $sub_id]);
                    $chapter_id = $pdo->lastInsertId();
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO mcqs 
                (chapter_id, category_slug, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, medium)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($approved_questions as $q) {
                // Skip if question was deselected by admin
                if (!isset($q['selected'])) continue;
                if (empty($q['question']) || empty($q['option_a'])) continue;

                $stmt->execute([
                    $chapter_id,
                    $category_slug,
                    trim($q['question']),
                    trim($q['option_a']),
                    trim($q['option_b']),
                    trim($q['option_c']),
                    trim($q['option_d']),
                    strtolower(trim($q['correct_answer'] ?? 'a')),
                    trim($q['explanation'] ?? ''),
                    trim($q['difficulty'] ?? 'medium'),
                    trim($q['medium'] ?? 'marathi')
                ]);
                $savedCount++;
            }
            $pdo->commit();
            $message = "🎉 अभिनंदन! $savedCount प्रश्न घटक (#$chapter_id - $topic_name) मध्ये सेव्ह झाले.";
            $messageType = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "प्रश्न सेव्ह करताना त्रुटी: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// B. Bulk Upload CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_csv_upload') {
    $chapter_id = intval($_POST['chapter_id'] ?? 0);
    $category_slug = trim($_POST['category_slug'] ?? 'scholarship');
    $medium = trim($_POST['medium'] ?? 'marathi');
    
    if ($chapter_id <= 0) {
        $message = "कृपया प्रश्न अपलोड करण्यासाठी घटक / धडा निवडा.";
        $messageType = "error";
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== 0) {
        $message = "कृपया वैध CSV फाईल निवडा.";
        $messageType = "error";
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        fgetcsv($handle); // skip header
        
        $count = 0;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO mcqs 
                (chapter_id, category_slug, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, medium)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            while (($row = fgetcsv($handle, 2000, ",")) !== FALSE) {
                if (empty(trim($row[0] ?? ''))) continue; // skip blank
                $qText = trim($row[0] ?? '');
                $optA = trim($row[1] ?? '');
                $optB = trim($row[2] ?? '');
                $optC = trim($row[3] ?? '');
                $optD = trim($row[4] ?? '');
                $corr = strtolower(trim($row[5] ?? 'a'));
                $exp = trim($row[6] ?? '');
                $diff = strtolower(trim($row[7] ?? 'medium'));
                
                $stmt->execute([
                    $chapter_id, $category_slug, $qText, $optA, $optB, $optC, $optD, $corr, $exp, $diff, $medium
                ]);
                $count++;
            }
            fclose($handle);
            $pdo->commit();
            $message = "📁 CSV मधून एकूण $count प्रश्न यशस्वीरित्या इंपोर्ट झाले!";
            $messageType = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "CSV इंपोर्ट त्रुटी: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// C. Sample CSV Download
if (isset($_GET['action']) && $_GET['action'] === 'download_sample_csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="scholarship_mcq_sample.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, ['Question (प्रश्न)', 'Option A (पर्याय अ)', 'Option B (पर्याय ब)', 'Option C (पर्याय क)', 'Option D (पर्याय ड)', 'Correct Answer (a/b/c/d)', 'Explanation (स्पष्टीकरण)', 'Difficulty (easy/medium/hard)']);
    fputcsv($out, ['खालीलपैकी विजोड पद ओळखा: 11, 13, 15, 17', '11', '13', '15', '17', 'c', '15 ही संयुक्त संख्या आहे, बाकी सर्व मूळ संख्या आहेत.', 'medium']);
    fputcsv($out, ['Choose the correct preposition: She is fond ___ classical music.', 'with', 'of', 'for', 'in', 'b', 'The adjective fond always takes the preposition of.', 'easy']);
    fputcsv($out, ['जर CAT = 24 तर DOG = ?', '26', '28', '30', '32', 'a', 'D(4) + O(15) + G(7) = 26', 'medium']);
    fclose($out);
    exit();
}

// D. Delete MCQ
if (isset($_GET['delete_mcq'])) {
    $mcq_id = intval($_GET['delete_mcq']);
    try {
        $pdo->prepare("DELETE FROM mcqs WHERE mcq_id = ?")->execute([$mcq_id]);
        $message = "प्रश्न यशस्वीरित्या डिलीट केला.";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "डिलीट करताना त्रुटी: " . $e->getMessage();
        $messageType = "error";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. DATA FOR VIEW (Chapters & Question Bank Explorer)
// ─────────────────────────────────────────────────────────────────────────────
$selected_cat = $_GET['cat'] ?? 'all';
$search_q = trim($_GET['search'] ?? '');

// Fetch Chapters for Dropdown
$chaptersList = [];
try {
    $chaptersList = $pdo->query("
        SELECT ch.chapter_id, ch.chapter_name, s.subject_name, c.class_name
        FROM chapters ch
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        ORDER BY s.subject_name, ch.chapter_name
    ")->fetchAll();
} catch (Exception $e) {}

// Fetch Question Counts by Category
$catCounts = ['all' => 0];
foreach (array_keys($CATEGORIES) as $k) $catCounts[$k] = 0;
try {
    $cStmt = $pdo->query("SELECT COALESCE(category_slug, 'scholarship') as cslug, COUNT(*) as cnt FROM mcqs GROUP BY COALESCE(category_slug, 'scholarship')");
    $allCnt = 0;
    while ($r = $cStmt->fetch()) {
        $s = $r['cslug'];
        $n = intval($r['cnt']);
        $catCounts[$s] = $n;
        $allCnt += $n;
    }
    $catCounts['all'] = $allCnt;
} catch (Exception $e) {}

// Fetch Explorer MCQs
$explorerMCQs = [];
try {
    $whereParts = ["1=1"];
    $whereParams = [];
    
    if ($selected_cat !== 'all') {
        $whereParts[] = "(m.category_slug = ? OR (m.category_slug IS NULL AND ? = 'scholarship'))";
        $whereParams[] = $selected_cat;
        $whereParams[] = $selected_cat;
    }
    if (!empty($search_q)) {
        $whereParts[] = "(m.question LIKE ? OR m.explanation LIKE ?)";
        $whereParams[] = "%$search_q%";
        $whereParams[] = "%$search_q%";
    }
    
    $whereSQL = implode(" AND ", $whereParts);
    $stmtE = $pdo->prepare("
        SELECT m.*, ch.chapter_name, s.subject_name
        FROM mcqs m
        LEFT JOIN chapters ch ON m.chapter_id = ch.chapter_id
        LEFT JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE $whereSQL
        ORDER BY m.mcq_id DESC
        LIMIT 60
    ");
    $stmtE->execute($whereParams);
    $explorerMCQs = $stmtE->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship & Competitive Exam Studio - Veeru Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f8fafc; color: #1e293b; }
        
        .header { background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); color: white; padding: 18px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.12); }
        .header h1 { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .btn-header { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .btn-header:hover { background: white; color: #4A00E0; }

        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav ul { list-style: none; display: flex; gap: 10px; overflow-x: auto; }
        .nav li a { display: block; padding: 14px 18px; color: #666; text-decoration: none; font-weight: 500; font-size: 14px; border-bottom: 3px solid transparent; }
        .nav li a.active { color: #8E2DE2; border-bottom-color: #8E2DE2; font-weight: 700; }

        .container { max-width: 1400px; margin: 25px auto; padding: 0 30px; }
        
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Stats Bar */
        .stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: white; padding: 18px 20px; border-radius: 14px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .stat-box .title { font-size: 12px; color: #64748b; font-weight: 600; }
        .stat-box .value { font-size: 24px; font-weight: 700; color: #0f172a; margin-top: 4px; }
        .stat-box .badge-pill { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; }

        /* Category Filter Tabs */
        .cat-scroll { display: flex; gap: 10px; margin-bottom: 24px; overflow-x: auto; padding-bottom: 6px; }
        .cat-btn { padding: 10px 18px; border-radius: 50px; background: white; color: #475569; text-decoration: none; font-size: 13px; font-weight: 600; border: 1.5px solid #e2e8f0; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; transition: 0.2s; }
        .cat-btn:hover { border-color: #8E2DE2; color: #8E2DE2; }
        .cat-btn.active { background: #8E2DE2; color: white; border-color: #8E2DE2; box-shadow: 0 4px 12px rgba(142, 45, 226, 0.3); }

        /* Hub Tabs */
        .hub-tabs-row { display: flex; gap: 10px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; }
        .hub-tab-btn { padding: 12px 24px; font-size: 15px; font-weight: 600; color: #64748b; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .hub-tab-btn.active { color: #8E2DE2; border-bottom-color: #8E2DE2; }

        /* Studio Panel Card */
        .studio-card { background: white; border-radius: 16px; padding: 28px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.04); margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #334155; }
        .form-control { width: 100%; padding: 11px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; transition: 0.2s; background: #fff; }
        .form-control:focus { outline: none; border-color: #8E2DE2; box-shadow: 0 0 0 3px rgba(142, 45, 226, 0.1); }
        
        .topic-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .chip { background: #f1f5f9; padding: 6px 12px; border-radius: 50px; font-size: 12px; color: #475569; cursor: pointer; border: 1px solid #e2e8f0; transition: 0.15s; }
        .chip:hover { background: #e0e7ff; color: #4338ca; border-color: #c7d2fe; }

        .btn-ai-generate { background: linear-gradient(135deg, #8E2DE2, #4A00E0); color: white; padding: 14px 28px; border-radius: 10px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(142, 45, 226, 0.35); transition: 0.2s; }
        .btn-ai-generate:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(142, 45, 226, 0.45); }
        .btn-ai-generate:disabled { opacity: 0.6; cursor: not-allowed; }

        /* Generated Questions Table / Cards */
        .generated-box { margin-top: 30px; display: none; }
        .generated-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .q-preview-card { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; position: relative; }
        .q-preview-card.selected { border-color: #8E2DE2; background: #faf5ff; }
        .opt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 12px 0; }
        .opt-item { background: white; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .opt-item.is-correct { border-color: #10b981; background: #ecfdf5; font-weight: 600; color: #065f46; }

        /* Question Explorer Table */
        .q-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .q-table th, .q-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .q-table th { background: #f8fafc; font-weight: 600; color: #475569; }
        .btn-del { color: #ef4444; text-decoration: none; font-weight: 600; }
        .btn-del:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="header">
    <h1>✨ स्पर्धा परीक्षा प्रश्न व अभ्यासक्रम स्टुडिओ (AI Studio)</h1>
    <div style="display: flex; gap: 12px;">
        <a href="scholarship_exams.php" class="btn-header">🏆 Live Mock Exams</a>
        <a href="dashboard.php" class="btn-header">⬅ Dashboard</a>
    </div>
</div>

<nav class="nav">
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="scholarship_hub.php" class="active">✨ Scholarship Studio (AI)</a></li>
        <li><a href="scholarship_exams.php">🏆 Scholarship Live Exams</a></li>
        <li><a href="mcqs.php">Standard MCQs</a></li>
        <li><a href="subjects.php">Subjects</a></li>
        <li><a href="chapters.php">Chapters</a></li>
    </ul>
</nav>

<div class="container">
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Category Stats Counter -->
    <div class="stats-bar">
        <div class="stat-box">
            <div>
                <div class="title">एकूण स्पर्धा प्रश्न</div>
                <div class="value"><?php echo $catCounts['all']; ?></div>
            </div>
            <span class="badge-pill" style="background:#e0e7ff; color:#4338ca;">All Exams</span>
        </div>
        <?php foreach ($CATEGORIES as $ck => $cdata): ?>
            <div class="stat-box">
                <div>
                    <div class="title"><?php echo htmlspecialchars($cdata['badge']); ?></div>
                    <div class="value"><?php echo $catCounts[$ck] ?? 0; ?> प्रश्न</div>
                </div>
                <span class="badge-pill" style="background:<?php echo $cdata['bg']; ?>; color:<?php echo $cdata['color']; ?>;">
                    <?php echo $ck; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Category Switcher Filter -->
    <div class="cat-scroll">
        <a href="scholarship_hub.php?cat=all" class="cat-btn <?php echo $selected_cat === 'all' ? 'active' : ''; ?>">
            🌟 सर्व परीक्षा (All)
        </a>
        <?php foreach ($CATEGORIES as $ck => $cdata): ?>
            <a href="scholarship_hub.php?cat=<?php echo $ck; ?>" class="cat-btn <?php echo $selected_cat === $ck ? 'active' : ''; ?>">
                <?php echo $cdata['badge']; ?> (<?php echo $catCounts[$ck] ?? 0; ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- 3 Studio Action Tabs -->
    <div class="hub-tabs-row">
        <button class="hub-tab-btn active" onclick="switchHubTab('ai_tab', this)">
            🤖 AI Question Studio (Instant Generator)
        </button>
        <button class="hub-tab-btn" onclick="switchHubTab('bulk_tab', this)">
            📁 CSV / Excel Bulk Upload
        </button>
        <button class="hub-tab-btn" onclick="switchHubTab('explorer_tab', this)">
            📋 Question Bank Explorer (<?php echo count($explorerMCQs); ?>)
        </button>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────────── -->
    <!-- TAB 1: AI QUESTION GENERATOR -->
    <!-- ───────────────────────────────────────────────────────────────────────────── -->
    <div id="ai_tab" class="studio-card">
        <div style="margin-bottom: 20px;">
            <h2 style="font-size: 18px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                🤖 AI द्वारे उच्च दर्जाचे MCQ प्रश्न तयार करा
            </h2>
            <p style="color: #64748b; font-size: 13px; margin-top: 4px;">
                बुद्धिमत्ता चाचणी (Logical Reasoning), इंग्रजी व्याकरण (English Grammar), अंकगणित (Arithmetic) व सामान्य ज्ञान या विषयांचे परीक्षा-मानक प्रश्न अवघ्या काही सेकंदात तयार करा.
            </p>
        </div>

        <form id="aiGenForm" onsubmit="handleAIGenerate(event)">
            <div class="form-row">
                <div class="form-group">
                    <label>परीक्षेचा प्रकार (Exam Category)</label>
                    <select id="aiCategory" class="form-control" onchange="updateSubjectsForCategory()">
                        <?php foreach ($CATEGORIES as $ck => $cdata): ?>
                            <option value="<?php echo $ck; ?>" <?php echo $selected_cat === $ck ? 'selected' : ''; ?>>
                                <?php echo $cdata['badge']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>इयत्ता / वर्ग (Target Grade)</label>
                    <select id="aiGrade" class="form-control">
                        <option value="5" selected>Class 5 (५वी - नवोदय / ५वी शिष्यवृत्ती / AISSEE 6th)</option>
                        <option value="8">Class 8 (८वी - ८वी शिष्यवृत्ती / AISSEE 9th / NMMS)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>विषय (Subject)</label>
                    <select id="aiSubject" class="form-control" onchange="updateTopicChips()">
                        <option value="Logical Reasoning" selected>बुद्धिमत्ता चाचणी (Mental Ability & Logic)</option>
                        <option value="English Grammar">इंग्रजी व्याकरण (English Grammar)</option>
                        <option value="Mathematics">अंकगणित व गणित (Arithmetic & Math)</option>
                        <option value="General Knowledge">सामान्य ज्ञान (General Knowledge)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>काठिण्य पातळी (Difficulty)</label>
                    <select id="aiDifficulty" class="form-control">
                        <option value="easy">Easy (प्राथमिक सराव)</option>
                        <option value="medium" selected>Medium (परीक्षा पातळी)</option>
                        <option value="hard">Hard (Achievers / HOTS)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label>घटक / टॉपिकचे नाव (Topic Name):</label>
                    <input type="text" id="aiTopic" class="form-control" value="सांकेतिक भाषा (Coding-Decoding)" required placeholder="उदा. नातेसंबंध, काळ व क्रियापद, अपूर्णांक">
                    <div class="topic-chips" id="topicChips">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <div class="form-group">
                    <label>माध्यम (Language / Medium)</label>
                    <select id="aiLanguage" class="form-control">
                        <option value="Marathi" selected>मराठी (Marathi Medium)</option>
                        <option value="English">English Medium</option>
                        <option value="Semi-English">Semi-English (द्विभाषिक)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>प्रश्नांची संख्या</label>
                    <select id="aiCount" class="form-control">
                        <option value="5" selected>५ प्रश्न (Quick 5)</option>
                        <option value="10">१० प्रश्न (Standard 10)</option>
                        <option value="15">१५ प्रश्न (15 Questions)</option>
                        <option value="20">२० प्रश्न (Full Section 20)</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: 10px;">
                <button type="submit" id="btnGenerateAI" class="btn-ai-generate">
                    <span>⚡ AI द्वारे प्रश्न जनरेट करा</span>
                </button>
            </div>
        </form>

        <!-- Preview & Approval Container -->
        <div id="aiResultsBox" class="generated-box">
            <div class="generated-header">
                <div>
                    <h3 style="font-size: 16px; color: #1e293b;">📋 जनरेट झालेले प्रश्न (Review & Approve):</h3>
                    <p style="font-size: 12px; color: #64748b;">खालील प्रश्नांची तपासणी करा, आवश्यक असल्यास दुरुस्ती करा आणि थेट डेटाबेसमध्ये सेव्ह करा.</p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" onclick="selectAllAIQuestions(true)" class="btn-header" style="background:#e2e8f0; color:#334155;">सर्व निवडा</button>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="save_approved_mcqs">
                <input type="hidden" name="category_slug" id="saveCategorySlug" value="scholarship">
                <input type="hidden" name="generated_subject" id="saveGenSubject" value="">
                <input type="hidden" name="generated_topic" id="saveGenTopic" value="">
                <input type="hidden" name="generated_grade" id="saveGenGrade" value="5">

                <div class="form-row" style="background: #f1f5f9; padding: 14px; border-radius: 10px; margin-bottom: 20px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>कोणत्या धड्यात / घटकात सेव्ह करायचे? (Select Chapter to Save): *</label>
                        <select name="chapter_id" class="form-control" required>
                            <option value="auto_create" selected>✨ नवीन घटक आपोआप तयार करा (Auto-create Chapter for Topic)</option>
                            <?php foreach ($chaptersList as $ch): ?>
                                <option value="<?php echo $ch['chapter_id']; ?>">
                                    [<?php echo htmlspecialchars($ch['subject_name']); ?>] <?php echo htmlspecialchars($ch['chapter_name']); ?> (<?php echo htmlspecialchars($ch['class_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="questionsContainer">
                    <!-- Populated dynamically by JS -->
                </div>

                <div style="margin-top: 24px; text-align: right;">
                    <button type="submit" class="btn-ai-generate" style="background: #10b981;">
                        💾 निवडलेले सर्व प्रश्न डेटाबेसमध्ये सेव्ह करा
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────────── -->
    <!-- TAB 2: BULK CSV UPLOAD -->
    <!-- ───────────────────────────────────────────────────────────────────────────── -->
    <div id="bulk_tab" class="studio-card" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="font-size: 18px; color: #1e293b;">📁 CSV / Excel द्वारे मोठ्या प्रमाणात प्रश्न अपलोड करा</h2>
                <p style="color: #64748b; font-size: 13px;">एकाच वेळी १००+ प्रश्न थेट Excel किंवा CSV फाईलवरून अपलोड करा.</p>
            </div>
            <a href="scholarship_hub.php?action=download_sample_csv" class="btn-header" style="background: #4A00E0; color: white;">
                📥 नमुना CSV फाईल डाऊनलोड करा
            </a>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="bulk_csv_upload">

            <div class="form-row">
                <div class="form-group">
                    <label>परीक्षेचा प्रकार (Exam Category)</label>
                    <select name="category_slug" class="form-control">
                        <?php foreach ($CATEGORIES as $ck => $cdata): ?>
                            <option value="<?php echo $ck; ?>"><?php echo $cdata['badge']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>घटक / धडा (Chapter)</label>
                    <select name="chapter_id" class="form-control" required>
                        <option value="">-- घटक निवडा --</option>
                        <?php foreach ($chaptersList as $ch): ?>
                            <option value="<?php echo $ch['chapter_id']; ?>">
                                [<?php echo htmlspecialchars($ch['subject_name']); ?>] <?php echo htmlspecialchars($ch['chapter_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>माध्यम (Medium)</label>
                    <select name="medium" class="form-control">
                        <option value="marathi">Marathi</option>
                        <option value="english">English</option>
                        <option value="semi_english">Semi-English</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-top: 10px;">
                <label>CSV फाईल निवडा (.csv format)</label>
                <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                <small style="color: #64748b; margin-top: 4px; display: block;">मराठी फॉन्ट व्यवस्थित येण्यासाठी फाईल UTF-8 एन्कोडिंगमध्ये सेव्ह करा.</small>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn-ai-generate" style="background: #0284C7;">
                    📤 CSV अपलोड व सेव्ह करा
                </button>
            </div>
        </form>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────────── -->
    <!-- TAB 3: QUESTION BANK EXPLORER -->
    <!-- ───────────────────────────────────────────────────────────────────────────── -->
    <div id="explorer_tab" class="studio-card" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="font-size: 18px; color: #1e293b;">📋 उपलब्ध प्रश्नसंच (Total Displayed: <?php echo count($explorerMCQs); ?>)</h2>
            <form method="GET" style="display: flex; gap: 8px;">
                <input type="hidden" name="cat" value="<?php echo htmlspecialchars($selected_cat); ?>">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="प्रश्नात शोधा..." class="form-control" style="width: 250px;">
                <button type="submit" class="btn-header" style="background: #8E2DE2; border:none; cursor:pointer;">शोधा</button>
            </form>
        </div>

        <?php if (empty($explorerMCQs)): ?>
            <div style="padding: 40px; text-align: center; color: #94a3b8;">
                कोणतेही प्रश्न उपलब्ध नाहीत. AI द्वारे नवीन प्रश्न तयार करा किंवा CSV अपलोड करा.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="q-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 12%;">कॅटेगरी</th>
                            <th style="width: 18%;">विषय / धडा</th>
                            <th>प्रश्न व पर्याय</th>
                            <th style="width: 10%;">अचूक उत्तर</th>
                            <th style="width: 8%;">काठिण्य</th>
                            <th style="width: 6%;">कृती</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($explorerMCQs as $m): 
                            $cKey = $m['category_slug'] ?? 'scholarship';
                            $cBadge = $CATEGORIES[$cKey]['badge'] ?? '📜 शिष्यवृत्ती';
                            $cBg = $CATEGORIES[$cKey]['bg'] ?? '#f1f5f9';
                            $cCol = $CATEGORIES[$cKey]['color'] ?? '#333';
                        ?>
                            <tr>
                                <td>#<?php echo $m['mcq_id']; ?></td>
                                <td>
                                    <span style="background: <?php echo $cBg; ?>; color: <?php echo $cCol; ?>; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                        <?php echo $cBadge; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($m['subject_name'] ?? ''); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($m['chapter_name'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 4px;"><?php echo htmlspecialchars($m['question']); ?></div>
                                    <div style="font-size: 11px; color: #64748b;">
                                        (A) <?php echo htmlspecialchars($m['option_a']); ?> | 
                                        (B) <?php echo htmlspecialchars($m['option_b']); ?> | 
                                        (C) <?php echo htmlspecialchars($m['option_c']); ?> | 
                                        (D) <?php echo htmlspecialchars($m['option_d']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 4px; font-weight: 700;">
                                        (<?php echo strtoupper($m['correct_answer']); ?>)
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 11px; text-transform: capitalize; color: #64748b;">
                                        <?php echo htmlspecialchars($m['difficulty']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="scholarship_hub.php?delete_mcq=<?php echo $m['mcq_id']; ?>&cat=<?php echo $selected_cat; ?>" onclick="return confirm('हा प्रश्न नक्की डिलीट करायचा?');" class="btn-del">
                                        🗑️
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const QUICK_TOPICS = <?php echo json_encode($QUICK_TOPICS); ?>;
const CATEGORY_SUBJECTS = {
    'scholarship': ['बुद्धिमत्ता चाचणी (Mental Ability)', 'गणित (Mathematics)', 'तृतीय भाषा (English Grammar)', 'मराठी / प्रथम भाषा'],
    'navodaya': ['मानसिक क्षमता चाचणी (Mental Ability)', 'अंकगणित (Arithmetic)', 'भाषा चाचणी (Language Reading)'],
    'sainik': ['गणित (Mathematics)', 'बुद्धिमत्ता चाचणी (Intelligence)', 'इंग्रजी व्याकरण (English Grammar)', 'सामान्य ज्ञान (General Knowledge)'],
    'olympiad': ['IMO - Mathematics', 'NSO - Science', 'IEO - English Grammar', 'Logical Reasoning'],
    'mts': ['MAT - मानसिक क्षमता चाचणी', 'SAT - विज्ञान (Science)', 'SAT - सामाजिक शास्त्रे', 'SAT - गणित (Mathematics)']
};

function switchHubTab(tabId, btn) {
    document.querySelectorAll('.hub-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('ai_tab').style.display = 'none';
    document.getElementById('bulk_tab').style.display = 'none';
    document.getElementById('explorer_tab').style.display = 'none';
    document.getElementById(tabId).style.display = 'block';
}

function updateSubjectsForCategory() {
    const cat = document.getElementById('aiCategory').value;
    const subSelect = document.getElementById('aiSubject');
    const subjects = CATEGORY_SUBJECTS[cat] || CATEGORY_SUBJECTS['scholarship'];
    
    subSelect.innerHTML = '';
    subjects.forEach((s, idx) => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        if (idx === 0) opt.selected = true;
        subSelect.appendChild(opt);
    });
    updateTopicChips();
}

function updateTopicChips() {
    const sub = document.getElementById('aiSubject').value;
    const chipsBox = document.getElementById('topicChips');
    chipsBox.innerHTML = '';
    
    let list = QUICK_TOPICS['Logical Reasoning'];
    if (sub.includes('English') || sub.includes('भाषा') || sub.includes('IEO')) {
        list = QUICK_TOPICS['English Grammar'];
    } else if (sub.includes('Math') || sub.includes('गणित') || sub.includes('अंकगणित') || sub.includes('IMO')) {
        list = QUICK_TOPICS['Mathematics'];
    }
    
    if (list) {
        list.forEach((t, i) => {
            const span = document.createElement('span');
            span.className = 'chip';
            span.textContent = t;
            span.onclick = () => { document.getElementById('aiTopic').value = t; };
            chipsBox.appendChild(span);
            if (i === 0) document.getElementById('aiTopic').value = t;
        });
    }
}
updateSubjectsForCategory();

// Handle AI Question Generation via AJAX
async function handleAIGenerate(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGenerateAI');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳ AI प्रश्न तयार करत आहे... (कृपया १०-१५ सेकंद थांबा)</span>';

    const formData = new FormData();
    formData.append('action', 'ai_generate_mcqs');
    formData.append('category_slug', document.getElementById('aiCategory').value);
    formData.append('target_class', document.getElementById('aiGrade').value);
    formData.append('subject', document.getElementById('aiSubject').value);
    formData.append('topic', document.getElementById('aiTopic').value);
    formData.append('difficulty', document.getElementById('aiDifficulty').value);
    formData.append('language', document.getElementById('aiLanguage').value);
    formData.append('count', document.getElementById('aiCount').value);

    try {
        const res = await fetch('scholarship_hub.php', { method: 'POST', body: formData });
        const json = await res.json();
        
        if (json.status === 'success' && json.data?.length > 0) {
            renderAIGeneratedQuestions(json.data, document.getElementById('aiCategory').value);
        } else {
            alert('त्रुटी: ' + (json.message || 'प्रश्न जनरेट होऊ शकले नाहीत.'));
        }
    } catch (err) {
        alert('सर्व्हरशी संपर्क करताना त्रुटी आली: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function escapeHtmlAttr(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function renderAIGeneratedQuestions(questions, categorySlug) {
    const container = document.getElementById('questionsContainer');
    document.getElementById('saveCategorySlug').value = categorySlug;
    document.getElementById('saveGenSubject').value = document.getElementById('aiSubject').value;
    document.getElementById('saveGenTopic').value = document.getElementById('aiTopic').value;
    document.getElementById('saveGenGrade').value = document.getElementById('aiGrade').value;
    container.innerHTML = '';

    questions.forEach((q, idx) => {
        const card = document.createElement('div');
        card.className = 'q-preview-card selected';
        card.id = `q_card_${idx}`;
        
        card.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <label style="font-weight:700; color:#8E2DE2; cursor:pointer;">
                    <input type="checkbox" name="questions[${idx}][selected]" value="1" checked onchange="toggleCardSelect(${idx}, this)">
                    प्रश्न #${idx + 1}
                </label>
                <span style="font-size:11px; background:#e0e7ff; color:#4338ca; padding:2px 8px; border-radius:4px; font-weight:600;">
                    ${escapeHtmlAttr(q.difficulty || 'medium')}
                </span>
            </div>
            
            <textarea name="questions[${idx}][question]" class="form-control" style="margin-bottom:8px; font-weight:600;" rows="2">${escapeHtmlAttr(q.question)}</textarea>
            
            <div class="opt-grid">
                <div class="opt-item ${q.correct_answer === 'a' ? 'is-correct' : ''}">
                    <span>(A)</span>
                    <input type="text" name="questions[${idx}][option_a]" value="${escapeHtmlAttr(q.option_a)}" class="form-control" style="border:none; padding:4px;">
                </div>
                <div class="opt-item ${q.correct_answer === 'b' ? 'is-correct' : ''}">
                    <span>(B)</span>
                    <input type="text" name="questions[${idx}][option_b]" value="${escapeHtmlAttr(q.option_b)}" class="form-control" style="border:none; padding:4px;">
                </div>
                <div class="opt-item ${q.correct_answer === 'c' ? 'is-correct' : ''}">
                    <span>(C)</span>
                    <input type="text" name="questions[${idx}][option_c]" value="${escapeHtmlAttr(q.option_c)}" class="form-control" style="border:none; padding:4px;">
                </div>
                <div class="opt-item ${q.correct_answer === 'd' ? 'is-correct' : ''}">
                    <span>(D)</span>
                    <input type="text" name="questions[${idx}][option_d]" value="${escapeHtmlAttr(q.option_d)}" class="form-control" style="border:none; padding:4px;">
                </div>
            </div>

            <div style="display:flex; gap:16px; align-items:center; margin-top:8px;">
                <div style="font-size:12px; font-weight:600; color:#334155;">अचूक उत्तर:</div>
                ${['a', 'b', 'c', 'd'].map(opt => `
                    <label style="font-size:12px; cursor:pointer;">
                        <input type="radio" name="questions[${idx}][correct_answer]" value="${opt}" ${q.correct_answer === opt ? 'checked' : ''}>
                        (${opt.toUpperCase()})
                    </label>
                `).join('')}
            </div>

            <div style="margin-top:10px;">
                <label style="font-size:11px; font-weight:600; color:#64748b;">स्पष्टीकरण (Explanation):</label>
                <input type="text" name="questions[${idx}][explanation]" value="${escapeHtmlAttr(q.explanation || '')}" class="form-control" style="font-size:12px;">
            </div>
            <input type="hidden" name="questions[${idx}][difficulty]" value="${escapeHtmlAttr(q.difficulty || 'medium')}">
        `;
        container.appendChild(card);
    });

    document.getElementById('aiResultsBox').style.display = 'block';
    document.getElementById('aiResultsBox').scrollIntoView({ behavior: 'smooth' });
}

function toggleCardSelect(idx, checkbox) {
    const card = document.getElementById(`q_card_${idx}`);
    if (checkbox.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
}

function selectAllAIQuestions(status) {
    document.querySelectorAll('#questionsContainer input[type="checkbox"]').forEach(cb => {
        cb.checked = status;
        cb.dispatchEvent(new Event('change'));
    });
}
</script>
</body>
</html>
