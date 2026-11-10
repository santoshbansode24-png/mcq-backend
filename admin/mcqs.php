<?php
/**
 * MCQs Management with Bulk Upload
 * Veeru
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Check for Board Selection
if (!isset($_SESSION['admin_selected_board'])) {
    header('Location: select_board.php');
    exit();
}
$selected_board = $_SESSION['admin_selected_board'];
$board_name = $_SESSION['board_name'];

require_once '../config/db.php';
if (file_exists('../config/ai_config.php')) {
    require_once '../config/ai_config.php';
} elseif (file_exists('../backend/config/ai_config.php')) {
    require_once '../backend/config/ai_config.php';
}

// Handle AJAX AI MCQ Generation
if (isset($_POST['action']) && $_POST['action'] === 'ai_generate_board_mcqs') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $topic = trim($_POST['topic'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $class_name = trim($_POST['class_name'] ?? '');
    $difficulty = trim($_POST['difficulty'] ?? 'medium');
    $medium = trim($_POST['medium'] ?? 'english');
    $count = min(15, max(1, intval($_POST['count'] ?? 5)));
    
    if (empty($topic) && empty($subject_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a topic or select a subject.']);
        exit();
    }
    
    $prompt = "You are an expert curriculum developer and MCQ author for Indian school and competitive examinations.
Board: {$board_name}
Class / Grade: {$class_name}
Subject: {$subject_name}
Topic / Chapter: {$topic}
Difficulty: {$difficulty}
Medium: {$medium}

Generate exactly {$count} multiple-choice questions (MCQs) for this topic.

RULES:
1. Each question must have exactly ONE unequivocally correct answer.
2. The remaining 3 options must be plausible distractors testing common misconceptions.
3. Provide a clear, step-by-step explanation teaching the underlying concept, rule, formula, or logic.
4. If Medium is Marathi ('marathi'), write fluent, natural Marathi with English terminology in brackets where appropriate.
5. If Subject is English Grammar, keep questions and options in English, with bilingual explanation.
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
      \"explanation\": \"Step-by-step explanation\",
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
            if (preg_match('/\{[\s\S]*\}/', $aiRaw, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
        }
        
        if (!isset($parsed['questions']) || empty($parsed['questions'])) {
            throw new Exception("AI returned empty or invalid question set.");
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $parsed['questions']
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Handle Sample CSV Download
if (isset($_GET['action']) && $_GET['action'] == 'download_sample') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="mcq_sample.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // Add BOM for Excel
    fputcsv($output, ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer (a/b/c/d)', 'Explanation', 'Difficulty (easy/medium/hard)']);
    fputcsv($output, ['What is 2+2?', '3', '4', '5', '6', 'b', '2 plus 2 equals 4', 'easy']);
    fputcsv($output, ['Identify this shape (example context)', 'Circle', 'Square', 'Triangle', 'Rectangle', 'c', 'It has 3 sides', 'medium']);
    fclose($output);
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Verify MCQ belongs to current board
    $check = $pdo->prepare("
        SELECT m.mcq_id FROM mcqs m
        JOIN chapters ch ON m.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE m.mcq_id = ? AND c.board_type = ?
    ");
    $check->execute([$id, $selected_board]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM mcqs WHERE mcq_id = ?");
        $stmt->execute([$id]);
    }
    header('Location: mcqs.php');
    exit();
}

$message = '';
$messageType = ''; // success or error

// Handle Save AI MCQs Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_ai_mcqs') {
    $chapter_id = intval($_POST['chapter_id'] ?? 0);
    $medium = isset($_POST['medium']) ? $_POST['medium'] : 'english';
    $questions = $_POST['questions'] ?? [];
    
    if ($chapter_id <= 0) {
        $message = "Please select a valid chapter before saving questions.";
        $messageType = "error";
    } elseif (empty($questions) || !is_array($questions)) {
        $message = "No questions selected to save.";
        $messageType = "error";
    } else {
        $savedCount = 0;
        try {
            $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, medium) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($questions as $q) {
                if (!isset($q['selected'])) continue;
                $qText = sanitizeInput($q['question'] ?? '');
                $optA = sanitizeInput($q['option_a'] ?? '');
                $optB = sanitizeInput($q['option_b'] ?? '');
                $optC = sanitizeInput($q['option_c'] ?? '');
                $optD = sanitizeInput($q['option_d'] ?? '');
                $ans = strtolower(trim($q['correct_answer'] ?? 'a'));
                $expl = sanitizeInput($q['explanation'] ?? '');
                $diff = strtolower(trim($q['difficulty'] ?? 'medium'));
                if (!in_array($ans, ['a','b','c','d'])) $ans = 'a';
                if (!in_array($diff, ['easy','medium','hard'])) $diff = 'medium';
                if (empty($qText) || empty($optA) || empty($optB)) continue;
                
                $stmt->execute([$chapter_id, $qText, $optA, $optB, $optC, $optD, $ans, $expl, $diff, $medium]);
                $savedCount++;
            }
            if ($savedCount > 0) {
                $message = "🎉 Successfully generated and saved {$savedCount} MCQs with AI to the selected chapter!";
                $messageType = "success";
            } else {
                $message = "No questions were selected or saved.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Handle Single Add MCQ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_single') {
    $chapter_id = intval($_POST['chapter_id']);
    $question = sanitizeInput($_POST['question']);
    $opt_a = sanitizeInput($_POST['option_a']);
    $opt_b = sanitizeInput($_POST['option_b']);
    $opt_c = sanitizeInput($_POST['option_c']);
    $opt_d = sanitizeInput($_POST['option_d']);
    $correct = $_POST['correct_answer'];
    $explanation = sanitizeInput($_POST['explanation']);
    $difficulty = $_POST['difficulty'];
    $medium = isset($_POST['medium']) ? $_POST['medium'] : 'english';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, medium) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$chapter_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $difficulty, $medium]);
        $message = "MCQ added successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Error: Database error - " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle Bulk Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_upload') {
    $chapter_id = intval($_POST['chapter_id']);
    
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        
        // Skip header row
        fgetcsv($handle);
        
        $count = 0;
        $errors = 0;
        
        // Get medium from form
        $medium = isset($_POST['medium']) ? $_POST['medium'] : 'english';
        
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, medium) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $row_num = 1;
        $first_error = "";
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_num++;
            
            // Check if row is practically empty (Excel saves trailing rows as ",,,,,,,")
            $is_empty_row = true;
            foreach ($data as $cell) {
                if (!empty(trim($cell ?? ''))) {
                    $is_empty_row = false;
                    break;
                }
            }
            if ($is_empty_row) {
                continue; // Silently skip without incrementing errors
            }
            
            // Validate row has enough columns (at least 6)
            if (count($data) < 6) { 
                if ($errors == 0) { $first_error = "Row $row_num: Not enough columns (" . count($data) . "). Is it comma-separated?"; }
                $errors++; continue; 
            }
            
            $question = sanitizeInput(convertUtf8($data[0]));
            if (empty($question)) {
                if ($errors == 0) { $first_error = "Row $row_num: Question is empty."; }
                $errors++; continue;
            }
            
            $opt_a = sanitizeInput(convertUtf8($data[1]));
            $opt_b = sanitizeInput(convertUtf8($data[2]));
            $opt_c = sanitizeInput(convertUtf8($data[3]));
            $opt_d = sanitizeInput(convertUtf8($data[4]));
            
            $correct = strtolower(trim(convertUtf8($data[5]))); // a, b, c, d
            $correct = str_replace(['option ', 'option'], '', $correct);
            
            $explanation = isset($data[6]) ? sanitizeInput(convertUtf8($data[6])) : '';
            
            $diff_input = isset($data[7]) ? strtolower(trim(convertUtf8($data[7]))) : '';
            $difficulty = empty($diff_input) ? 'medium' : $diff_input;
            if (!in_array($difficulty, ['easy', 'medium', 'hard'])) { $difficulty = 'medium'; }
            
            // Validate correct answer format
            if (!in_array($correct, ['a', 'b', 'c', 'd'])) { 
                if ($errors == 0) { $first_error = "Row $row_num: Invalid correct answer '$correct'"; }
                $errors++; continue; 
            }
            
            try {
                $stmt->execute([$chapter_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $difficulty, $medium]);
                $count++;
            } catch (Exception $e) {
                if ($errors == 0) { $first_error = "Row $row_num DB Error: " . $e->getMessage(); }
                $errors++;
            }
        }
        fclose($handle);
        
        $message = "Bulk upload complete! Added: $count MCQs. Skipped/Errors: $errors.";
        if (!empty($first_error)) {
            $message .= " Example error: " . $first_error;
        }
        $messageType = ($count > 0) ? "success" : "error";
    } else {
        $message = "Please upload a valid CSV file.";
        $messageType = "error";
    }
}

// Get Classes for Initial Dropdown
// Get Classes for Initial Dropdown
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();

$all_subjects_query = $pdo->prepare("
    SELECT s.* FROM subjects s 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE c.board_type = ? 
    ORDER BY s.subject_name
");
$all_subjects_query->execute([$selected_board]);
$all_subjects = $all_subjects_query->fetchAll();

$all_chapters_query = $pdo->prepare("
    SELECT ch.* FROM chapters ch 
    JOIN subjects s ON ch.subject_id = s.subject_id 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE c.board_type = ? 
    ORDER BY ch.chapter_order
");
$all_chapters_query->execute([$selected_board]);
$all_chapters = $all_chapters_query->fetchAll();

// Get MCQs List
$mcqs_query = $pdo->prepare("
    SELECT m.*, ch.chapter_name, s.subject_name
    FROM mcqs m
    JOIN chapters ch ON m.chapter_id = ch.chapter_id
    JOIN subjects s ON ch.subject_id = s.subject_id
    JOIN classes c ON s.class_id = c.class_id
    WHERE c.board_type = ?
    ORDER BY m.mcq_id DESC LIMIT 50
");
$mcqs_query->execute([$selected_board]);
$mcqs = $mcqs_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage MCQs - MCQ Admin</title>
    <style>
        /* Reusing Dashboard Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav ul { list-style: none; display: flex; gap: 5px; }
        .nav li a { display: block; padding: 18px 25px; color: #666; text-decoration: none; font-weight: 500; border-bottom: 3px solid transparent; }
        .nav li a:hover, .nav li a.active { color: #667eea; border-bottom-color: #667eea; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 40px; }
        
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { color: #666; font-weight: 600; background: #f9f9f9; }
        .btn-delete { color: #ff4444; text-decoration: none; font-weight: 500; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .btn-add { background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        .alert { padding: 10px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        /* Tabs */
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .tab-btn { padding: 10px 20px; background: none; border: none; font-size: 16px; cursor: pointer; color: #666; border-bottom: 3px solid transparent; }
        .tab-btn.active { color: #667eea; border-bottom-color: #667eea; font-weight: 600; }
        #ai-btn.active { color: #7C3AED !important; border-bottom-color: #7C3AED !important; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .btn-download { background: #28a745; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; display: inline-block; margin-bottom: 15px; font-size: 14px; }
        
        /* AI Generator Styles */
        .ai-banner {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            border: 1px solid #ddd6fe;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }
        .ai-banner h3 { color: #5b21b6; font-size: 16px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .ai-banner p { color: #6d28d9; font-size: 13px; margin: 0; }
        .quick-chips-wrapper { margin: 10px 0 15px 0; }
        .quick-chips-label { font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 6px; }
        .quick-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .quick-chip {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #374151;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .quick-chip:hover {
            background: #7c3aed;
            color: white;
            border-color: #7c3aed;
            transform: translateY(-1px);
        }
        .btn-ai-generate {
            background: linear-gradient(135deg, #7C3AED 0%, #4F46E5 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
            transition: all 0.2s ease;
        }
        .btn-ai-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.45);
        }
        .ai-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .ai-q-card {
            background: #faf5ff;
            border: 1px solid #e9d5ff;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
            transition: all 0.2s ease;
        }
        .ai-q-card:hover {
            border-color: #c084fc;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.12);
        }
        .ai-q-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .ai-q-badge {
            background: #7c3aed;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }
        
        /* Centered Switch Board Button */
        .header { position: relative; }
        .center-actions {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        .btn-switch-board {
            background: #ff9f43; /* Bright Orange */
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid white;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-switch-board:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            background: #ffcd19; /* Lighter Orange */
            color: #333;
        }
    </style>
    <script>
        const subjects = <?php echo json_encode($all_subjects); ?>;
        const chapters = <?php echo json_encode($all_chapters); ?>;

        function filterSubjects(prefix) {
            const classId = document.getElementById(prefix + 'class_select').value;
            const subjectSelect = document.getElementById(prefix + 'subject_select');
            const chapterSelect = document.getElementById(prefix + 'chapter_select');
            
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            chapterSelect.innerHTML = '<option value="">Select Chapter (Choose Subject First)</option>';
            
            subjects.forEach(subject => {
                if (subject.class_id == classId) {
                    const option = document.createElement('option');
                    option.value = subject.subject_id;
                    option.textContent = subject.subject_name;
                    subjectSelect.appendChild(option);
                }
            });
        }

        function filterChapters(prefix) {
            const subjectId = document.getElementById(prefix + 'subject_select').value;
            const chapterSelect = document.getElementById(prefix + 'chapter_select');
            
            chapterSelect.innerHTML = '<option value="">Select Chapter</option>';
            
            chapters.forEach(chapter => {
                if (chapter.subject_id == subjectId) {
                    const option = document.createElement('option');
                    option.value = chapter.chapter_id;
                    option.textContent = chapter.chapter_name;
                    chapterSelect.appendChild(option);
                }
            });
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            const targetContent = document.getElementById(tabName + '-content');
            const targetBtn = document.getElementById(tabName + '-btn');
            if (targetContent) targetContent.classList.add('active');
            if (targetBtn) targetBtn.classList.add('active');
        }

        function setAITopic(topic) {
            const input = document.getElementById('ai_topic_input');
            if (input) {
                input.value = topic;
                input.focus();
            }
        }

        function toggleSelectAllAI(master) {
            const boxes = document.querySelectorAll('.ai-question-checkbox');
            boxes.forEach(b => b.checked = master.checked);
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function generateMCQsAI() {
            const classSelect = document.getElementById('ai_class_select');
            const classId = classSelect.value;
            const className = classSelect.options[classSelect.selectedIndex]?.text || '';
            
            const subjectSelect = document.getElementById('ai_subject_select');
            const subjectName = subjectSelect.options[subjectSelect.selectedIndex]?.text || '';
            
            const chapterSelect = document.getElementById('ai_chapter_select');
            const chapterId = chapterSelect.value;
            
            const topic = document.getElementById('ai_topic_input').value.trim();
            const difficulty = document.getElementById('ai_difficulty_select').value;
            const medium = document.getElementById('ai_medium_select').value;
            const count = document.getElementById('ai_count_select').value;
            
            const statusBox = document.getElementById('ai-status-box');
            const resultsContainer = document.getElementById('ai-results-container');
            const btn = document.getElementById('btn-generate-ai');
            
            if (!classId) {
                alert('Please select a Class first.');
                return;
            }
            if (!subjectSelect.value) {
                alert('Please select a Subject.');
                return;
            }
            if (!chapterId) {
                alert('Please select a Chapter so generated questions can be saved to it.');
                return;
            }
            if (!topic && !subjectName) {
                alert('Please enter a Topic or select a Subject.');
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<span class="ai-spinner"></span> Generating questions with Gemini AI...';
            statusBox.style.display = 'block';
            statusBox.innerHTML = '<div style="color: #6d28d9; font-weight: 600; padding: 12px; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px;">🤖 Gemini 2.5 Flash is creating curriculum-aligned questions for "<strong>' + escapeHtml(topic || subjectName) + '</strong>"...</div>';
            resultsContainer.innerHTML = '';
            
            try {
                const formData = new FormData();
                formData.append('action', 'ai_generate_board_mcqs');
                formData.append('class_name', className);
                formData.append('subject_name', subjectName);
                formData.append('chapter_id', chapterId);
                formData.append('topic', topic);
                formData.append('difficulty', difficulty);
                formData.append('medium', medium);
                formData.append('count', count);
                
                const res = await fetch('mcqs.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.status === 'success' && data.data && data.data.length > 0) {
                    statusBox.innerHTML = '<div style="color: #15803d; font-weight: 600; padding: 12px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px;">🎉 Successfully generated ' + data.data.length + ' questions! Review, edit if needed, and click save below.</div>';
                    renderAIQuestions(data.data, chapterId, medium);
                } else {
                    statusBox.innerHTML = '<div style="color: #b91c1c; font-weight: 600; padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;">❌ ' + escapeHtml(data.message || 'Failed to generate questions. Please try again.') + '</div>';
                }
            } catch (err) {
                statusBox.innerHTML = '<div style="color: #b91c1c; font-weight: 600; padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;">❌ Network or server error: ' + escapeHtml(err.message) + '</div>';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '✨ Generate Questions with AI';
            }
        }

        function renderAIQuestions(questions, chapterId, medium) {
            const container = document.getElementById('ai-results-container');
            let html = '<form method="POST">';
            html += '<input type="hidden" name="action" value="save_ai_mcqs">';
            html += '<input type="hidden" name="chapter_id" value="' + escapeHtml(chapterId) + '">';
            html += '<input type="hidden" name="medium" value="' + escapeHtml(medium) + '">';
            
            html += '<div style="display:flex; justify-content:space-between; align-items:center; margin: 20px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #e9d5ff;">';
            html += '<label style="font-weight: 700; color: #4c1d95; font-size: 15px; display:flex; align-items:center; gap:8px; cursor:pointer;">';
            html += '<input type="checkbox" checked onchange="toggleSelectAllAI(this)" style="width:18px; height:18px;"> Select All (' + questions.length + ' questions)';
            html += '</label>';
            html += '<button type="submit" class="btn-ai-generate" style="padding: 10px 20px; font-size: 14px;">💾 Save Selected MCQs to Chapter</button>';
            html += '</div>';
            
            questions.forEach((q, idx) => {
                html += '<div class="ai-q-card">';
                html += '  <div class="ai-q-header">';
                html += '    <label style="display:flex; align-items:center; gap:8px; font-weight:700; color:#5b21b6; cursor:pointer;">';
                html += '      <input type="checkbox" name="questions[' + idx + '][selected]" value="1" checked class="ai-question-checkbox" style="width:18px; height:18px;">';
                html += '      Question #' + (idx + 1);
                html += '    </label>';
                html += '    <span class="ai-q-badge">' + escapeHtml((q.difficulty || 'medium').toUpperCase()) + '</span>';
                html += '  </div>';
                
                html += '  <div style="margin-bottom:10px;">';
                html += '    <textarea name="questions[' + idx + '][question]" style="width:100%; height:70px; font-weight:600; padding:8px; border:1px solid #c4b5fd; border-radius:6px;" required>' + escapeHtml(q.question) + '</textarea>';
                html += '  </div>';
                
                html += '  <div class="form-grid" style="margin-bottom: 10px;">';
                html += '    <div><label style="font-size:12px; font-weight:600; color:#6b7280;">Option A:</label><input type="text" name="questions[' + idx + '][option_a]" value="' + escapeHtml(q.option_a) + '" required></div>';
                html += '    <div><label style="font-size:12px; font-weight:600; color:#6b7280;">Option B:</label><input type="text" name="questions[' + idx + '][option_b]" value="' + escapeHtml(q.option_b) + '" required></div>';
                html += '    <div><label style="font-size:12px; font-weight:600; color:#6b7280;">Option C:</label><input type="text" name="questions[' + idx + '][option_c]" value="' + escapeHtml(q.option_c) + '" required></div>';
                html += '    <div><label style="font-size:12px; font-weight:600; color:#6b7280;">Option D:</label><input type="text" name="questions[' + idx + '][option_d]" value="' + escapeHtml(q.option_d) + '" required></div>';
                html += '  </div>';
                
                html += '  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 10px;">';
                html += '    <div>';
                html += '      <label style="font-size:12px; font-weight:600; color:#6b7280;">Correct Answer:</label>';
                html += '      <select name="questions[' + idx + '][correct_answer]" style="background:#ecfdf5; font-weight:700; border-color:#6ee7b7; color:#065f46;">';
                const ans = (q.correct_answer || 'a').toLowerCase();
                html += '        <option value="a"' + (ans === 'a' ? ' selected' : '') + '>Option A is Correct</option>';
                html += '        <option value="b"' + (ans === 'b' ? ' selected' : '') + '>Option B is Correct</option>';
                html += '        <option value="c"' + (ans === 'c' ? ' selected' : '') + '>Option C is Correct</option>';
                html += '        <option value="d"' + (ans === 'd' ? ' selected' : '') + '>Option D is Correct</option>';
                html += '      </select>';
                html += '    </div>';
                html += '    <div>';
                html += '      <label style="font-size:12px; font-weight:600; color:#6b7280;">Difficulty:</label>';
                html += '      <select name="questions[' + idx + '][difficulty]">';
                const diff = (q.difficulty || 'medium').toLowerCase();
                html += '        <option value="easy"' + (diff === 'easy' ? ' selected' : '') + '>Easy</option>';
                html += '        <option value="medium"' + (diff === 'medium' ? ' selected' : '') + '>Medium</option>';
                html += '        <option value="hard"' + (diff === 'hard' ? ' selected' : '') + '>Hard</option>';
                html += '      </select>';
                html += '    </div>';
                html += '  </div>';
                
                html += '  <div>';
                html += '    <label style="font-size:12px; font-weight:600; color:#6b7280;">Explanation:</label>';
                html += '    <textarea name="questions[' + idx + '][explanation]" style="width:100%; height:55px; font-size:13px; padding:6px; border:1px solid #e5e7eb; border-radius:6px;">' + escapeHtml(q.explanation || '') + '</textarea>';
                html += '  </div>';
                html += '</div>';
            });
            
            html += '<div style="text-align: right; margin-top: 15px;">';
            html += '  <button type="submit" class="btn-ai-generate" style="padding: 12px 28px; font-size: 15px;">💾 Save Selected MCQs to Chapter</button>';
            html += '</div>';
            html += '</form>';
            
            container.innerHTML = html;
        }

        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'ai';
            switchTab(tab);
        });
    </script>
</head>
<body>
    <div class="header">
        <h1>🎓 MCQ Admin Panel</h1>
        
        <!-- Centered Switch Button -->
        <div class="center-actions">
            <a href="select_board.php" class="btn-switch-board">
                🔁 Switch Board
            </a>
        </div>

        <div class="header-right">
            <div class="admin-info">
                <div class="name" style="margin-bottom: 3px;">
                    <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; font-size: 13px;">
                        <?php echo htmlspecialchars($board_name); ?>
                    </span>
                    &nbsp; <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                </div>
                <div class="email"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></div>
            </div>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="scholarship_hub.php" style="color: #7C3AED; font-weight: 700;">✨ Scholarship Studio (AI)</a></li>
            <li><a href="scholarship_exams.php" style="color: #4F46E5; font-weight: 600;">🏆 Scholarship Exams</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="classes.php">Classes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php" class="active">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
            <li><a href="data_verification.php">Verify Data</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
            <li><a href="audit_center.php">Audit Center</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="tabs" style="align-items: center;">
                <button class="tab-btn active" id="ai-btn" onclick="switchTab('ai')" style="color: #7C3AED; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                    🤖 AI Question Generator
                    <span style="background: #EDE9FE; color: #6D28D9; font-size: 11px; padding: 2px 7px; border-radius: 6px; font-weight: 700;">GEMINI AI</span>
                </button>
                <button class="tab-btn" id="single-btn" onclick="switchTab('single')">Add Single MCQ</button>
                <button class="tab-btn" id="bulk-btn" onclick="switchTab('bulk')">Bulk Upload (CSV)</button>

                <div style="margin-left: auto;">
                    <a href="scholarship_hub.php" style="text-decoration: none; padding: 8px 16px; background: linear-gradient(135deg, #7C3AED 0%, #4F46E5 100%); color: white; border-radius: 8px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(124, 58, 237, 0.25);">
                        ✨ Full Scholarship Studio (AI) &rarr;
                    </a>
                </div>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- AI Generator Form Tab -->
            <div id="ai-content" class="tab-content active">
                <div class="ai-banner">
                    <div>
                        <h3>🤖 AI Instant Question Generator (Gemini 2.5 Flash)</h3>
                        <p>Generate authentic, conceptual, and error-free MCQs with detailed explanations for any Class, Subject, and Chapter instantly.</p>
                    </div>
                    <a href="scholarship_hub.php" style="flex-shrink: 0; text-decoration: none; padding: 9px 18px; background: #7C3AED; color: white; border-radius: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);">
                        🏆 Open Scholarship Hub &rarr;
                    </a>
                </div>

                <div class="form-grid">
                    <select id="ai_class_select" onchange="filterSubjects('ai_')" required style="grid-column: span 2;">
                        <option value="">Select Class / Grade</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="ai_subject_select" onchange="filterChapters('ai_')" required>
                        <option value="">Select Subject</option>
                    </select>

                    <select id="ai_chapter_select" required>
                        <option value="">Select Chapter</option>
                    </select>

                    <div style="grid-column: span 2;">
                        <label style="display:block; margin-bottom:5px; font-weight:600; color:#374151;">Topic / Concept or Syllabus Sub-heading:</label>
                        <input type="text" id="ai_topic_input" placeholder="e.g. Coding-Decoding, Tenses & Prepositions, Fractions, LCM & HCF, Alphabet Series...">
                        
                        <div class="quick-chips-wrapper">
                            <div class="quick-chips-label">💡 Recommended High-Yield Topics (Click to fill):</div>
                            <div class="quick-chips">
                                <span class="quick-chip" onclick="setAITopic('अक्षरमाला (Alphabet Series)')">अक्षरमाला (Alphabet Series)</span>
                                <span class="quick-chip" onclick="setAITopic('संख्यामाला (Number Series)')">संख्यामाला (Number Series)</span>
                                <span class="quick-chip" onclick="setAITopic('सांकेतिक भाषा (Coding-Decoding)')">सांकेतिक भाषा (Coding-Decoding)</span>
                                <span class="quick-chip" onclick="setAITopic('नातेसंबंध (Blood Relations)')">नातेसंबंध (Blood Relations)</span>
                                <span class="quick-chip" onclick="setAITopic('विजोड पद (Odd Man Out)')">विजोड पद (Odd Man Out)</span>
                                <span class="quick-chip" onclick="setAITopic('Tenses & Correct Verb Forms')">Tenses & Verb Forms</span>
                                <span class="quick-chip" onclick="setAITopic('Articles & Prepositions')">Articles & Prepositions</span>
                                <span class="quick-chip" onclick="setAITopic('अपूर्णांक (Fractions & Decimals)')">अपूर्णांक (Fractions)</span>
                                <span class="quick-chip" onclick="setAITopic('लसावि व मसावि (LCM & HCF)')">LCM & HCF</span>
                                <span class="quick-chip" onclick="setAITopic('नफा व तोटा (Profit & Loss)')">नफा व तोटा (Profit & Loss)</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600; color:#374151;">Difficulty Level:</label>
                        <select id="ai_difficulty_select">
                            <option value="easy">Easy (पायाभूत / Basic)</option>
                            <option value="medium" selected>Medium (मध्यम / Standard)</option>
                            <option value="hard">Hard (काठिण्य पातळी / HOTS)</option>
                        </select>
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600; color:#374151;">Medium / Language:</label>
                        <select id="ai_medium_select" style="background: #fff3cd; border: 2px solid #ffc107;">
                            <option value="english">🇬🇧 English Medium</option>
                            <option value="marathi">🇮🇳 Marathi Medium (मराठी)</option>
                        </select>
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:5px; font-weight:600; color:#374151;">Number of Questions:</label>
                        <select id="ai_count_select">
                            <option value="3">3 Questions</option>
                            <option value="5" selected>5 Questions (Recommended)</option>
                            <option value="10">10 Questions</option>
                        </select>
                    </div>

                    <div style="display:flex; align-items:flex-end;">
                        <button type="button" id="btn-generate-ai" onclick="generateMCQsAI()" class="btn-ai-generate" style="width: 100%;">
                            ✨ Generate Questions with AI
                        </button>
                    </div>
                </div>

                <div id="ai-status-box" style="display:none; margin: 15px 0;"></div>
                <div id="ai-results-container"></div>
            </div>

            <!-- Single Add Form -->
            <div id="single-content" class="tab-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_single">
                    <div class="form-grid">
                        <select id="single_class_select" onchange="filterSubjects('single_')" required style="grid-column: span 2;">
                            <option value="">Select Class</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="single_subject_select" onchange="filterChapters('single_')" required>
                            <option value="">Select Subject</option>
                        </select>

                        <select name="chapter_id" id="single_chapter_select" required>
                            <option value="">Select Chapter</option>
                        </select>
                        
                        <div style="grid-column: span 2;">
                             <label style="display:block; margin-bottom:5px; font-weight:600;">Question Text:</label>
                             <textarea name="question" placeholder="Enter question here (or use LaTeX like \sqrt{x})" required style="width:100%; height: 80px;"></textarea>
                        </div>
                        
                        <input type="text" name="option_a" placeholder="Option A (e.g. \frac{1}{2})" required>
                        <input type="text" name="option_b" placeholder="Option B" required>
                        <input type="text" name="option_c" placeholder="Option C" required>
                        <input type="text" name="option_d" placeholder="Option D" required>
                        
                        <select name="correct_answer" required>
                            <option value="">Correct Answer</option>
                            <option value="a">Option A</option>
                            <option value="b">Option B</option>
                            <option value="c">Option C</option>
                            <option value="d">Option D</option>
                        </select>
                        
                        <select name="difficulty">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                        
                        <select name="medium" style="background: #fff3cd; border: 2px solid #ffc107;">
                            <option value="english">🇬🇧 English</option>
                            <option value="marathi">🇮🇳 Marathi (मराठी)</option>
                        </select>
                        
                        <textarea name="explanation" placeholder="Explanation (Optional)" style="grid-column: span 2;"></textarea>
                    </div>
                    <button type="submit" class="btn-add">Add MCQ</button>
                </form>
            </div>

            <!-- Bulk Upload Form -->
            <div id="bulk-content" class="tab-content">
                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h3>📝 Instructions</h3>
                    <p style="margin: 10px 0; color: #666;">1. Download the sample CSV file.<br>2. Fill in your questions (keep the header row).<br>3. Select the Class, Subject, and Chapter below.<br>4. Upload the file.</p>
                    <a href="?action=download_sample" class="btn-download">⬇️ Download Sample CSV</a>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="bulk_upload">
                    <div class="form-grid">
                        <select id="bulk_class_select" onchange="filterSubjects('bulk_')" required style="grid-column: span 2;">
                            <option value="">Select Class</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="bulk_subject_select" onchange="filterChapters('bulk_')" required>
                            <option value="">Select Subject</option>
                        </select>

                        <select name="chapter_id" id="bulk_chapter_select" required>
                            <option value="">Select Chapter</option>
                        </select>
                        
                        <select name="medium" required style="background: #fff3cd; border: 2px solid #ffc107; grid-column: span 2;">
                            <option value="english">🇬🇧 English Medium</option>
                            <option value="marathi">🇮🇳 Marathi Medium (मराठी)</option>
                        </select>

                        <div style="grid-column: span 2;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Upload CSV File:</label>
                            <input type="file" name="csv_file" accept=".csv" required style="padding: 10px; background: white;">
                        </div>
                    </div>
                    <button type="submit" class="btn-add">Upload MCQs</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Recent MCQs</h2>
            <table>
                <thead>
                    <tr>
                        <th width="30%">Question</th>
                        <th>Chapter</th>
                        <th>Answer</th>
                        <th>Difficulty</th>
                        <th>Medium</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($mcqs as $mcq): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($mcq['question'], 0, 50)) . '...'; ?></td>
                        <td>
                            <small><?php echo htmlspecialchars($mcq['subject_name']); ?></small><br>
                            <?php echo htmlspecialchars($mcq['chapter_name']); ?>
                        </td>
                        <td><strong><?php echo strtoupper($mcq['correct_answer']); ?></strong></td>
                        <td><?php echo ucfirst($mcq['difficulty']); ?></td>
                        <td>
                            <?php 
                            $medium = isset($mcq['medium']) ? $mcq['medium'] : 'english';
                            echo $medium == 'marathi' ? '🇮🇳 MR' : '🇬🇧 EN';
                            ?>
                        </td>
                        <td>
                            <a href="?delete=<?php echo $mcq['mcq_id']; ?>" class="btn-delete" onclick="return confirm('Delete this MCQ?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
