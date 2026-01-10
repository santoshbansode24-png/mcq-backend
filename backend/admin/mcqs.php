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
require_once '../config/db.php';

// Handle Sample CSV Download
if (isset($_GET['action']) && $_GET['action'] == 'download_sample') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="mcq_sample.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer (a/b/c/d)', 'Explanation', 'Difficulty (easy/medium/hard)']);
    fputcsv($output, ['What is 2+2?', '3', '4', '5', '6', 'b', '2 plus 2 equals 4', 'easy']);
    fputcsv($output, ['Capital of France?', 'London', 'Berlin', 'Paris', 'Madrid', 'c', 'Paris is the capital', 'medium']);
    fclose($output);
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM mcqs WHERE mcq_id = ?");
    $stmt->execute([$id]);
    header('Location: mcqs.php');
    exit();
}

$message = '';
$messageType = ''; // success or error

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
    
    try {
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$chapter_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $difficulty]);
        $message = "MCQ added successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Error: Database error";
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
        
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Validate row has enough columns (at least 6)
            if (count($data) < 6) { $errors++; continue; }
            
            $question = sanitizeInput($data[0]);
            $opt_a = sanitizeInput($data[1]);
            $opt_b = sanitizeInput($data[2]);
            $opt_c = sanitizeInput($data[3]);
            $opt_d = sanitizeInput($data[4]);
            $correct = strtolower(trim($data[5])); // a, b, c, d
            $explanation = isset($data[6]) ? sanitizeInput($data[6]) : '';
            $difficulty = isset($data[7]) ? strtolower(trim($data[7])) : 'medium';
            
            // Validate correct answer format
            if (!in_array($correct, ['a', 'b', 'c', 'd'])) { $errors++; continue; }
            
            try {
                $stmt->execute([$chapter_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $difficulty]);
                $count++;
            } catch (Exception $e) {
                $errors++;
            }
        }
        fclose($handle);
        
        $message = "Bulk upload complete! Added: $count MCQs. Skipped/Errors: $errors.";
        $messageType = ($count > 0) ? "success" : "error";
    } else {
        $message = "Please upload a valid CSV file.";
        $messageType = "error";
    }
}

// Get Classes for Initial Dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id")->fetchAll();
$all_subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
$all_chapters = $pdo->query("SELECT * FROM chapters ORDER BY chapter_order")->fetchAll();

// Get MCQs List
$mcqs = $pdo->query("
    SELECT m.*, ch.chapter_name, s.subject_name
    FROM mcqs m
    JOIN chapters ch ON m.chapter_id = ch.chapter_id
    JOIN subjects s ON ch.subject_id = s.subject_id
    ORDER BY m.mcq_id DESC LIMIT 50
")->fetchAll();
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
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .btn-download { background: #28a745; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; display: inline-block; margin-bottom: 15px; font-size: 14px; }
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
            
            document.getElementById(tabName + '-content').classList.add('active');
            document.getElementById(tabName + '-btn').classList.add('active');
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>🎓 MCQ Admin Panel</h1>
        <a href="logout.php" style="color: white; text-decoration: none;">Logout</a>
    </div>
    
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="classes.php">Classes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php" class="active">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="tabs">
                <button class="tab-btn active" id="single-btn" onclick="switchTab('single')">Add Single MCQ</button>
                <button class="tab-btn" id="bulk-btn" onclick="switchTab('bulk')">Bulk Upload (CSV)</button>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Single Add Form -->
            <div id="single-content" class="tab-content active">
                <form method="POST">
                    <input type="hidden" name="action" value="add_single">
                    <div class="form-grid">
                        <select id="single_class_select" onchange="filterSubjects('single_')" required style="grid-column: span 2;">
                            <option value="">Select Class</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="single_subject_select" onchange="filterChapters('single_')" required>
                            <option value="">Select Subject</option>
                        </select>

                        <select name="chapter_id" id="single_chapter_select" required>
                            <option value="">Select Chapter</option>
                        </select>
                        
                        <textarea name="question" placeholder="Question Text" required style="grid-column: span 2; height: 80px;"></textarea>
                        
                        <input type="text" name="option_a" placeholder="Option A" required>
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
                                <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="bulk_subject_select" onchange="filterChapters('bulk_')" required>
                            <option value="">Select Subject</option>
                        </select>

                        <select name="chapter_id" id="bulk_chapter_select" required>
                            <option value="">Select Chapter</option>
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
