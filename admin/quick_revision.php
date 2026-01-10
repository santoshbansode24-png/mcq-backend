<?php
/**
 * Quick Revision Management
 * Veeru
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}
require_once '../config/db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM quick_revision WHERE revision_id = ?");
    $stmt->execute([$id]);
    header('Location: quick_revision.php');
    exit();
}

// Handle Add Revision
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $chapter_id = intval($_POST['chapter_id']);
    $title = sanitizeInput($_POST['title']);
    $summary = sanitizeInput($_POST['summary']);
    $key_points = [];

    // 1. Handle CSV Upload if present
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        
        // Read file content
        $content = file_get_contents($file);
        
        // Detect and Convert to UTF-8 (Vital for Marathi/Hindi text)
        // This handles cases where Excel saves as ANSI or other encodings
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }

        // Split into lines (handle different line endings)
        $lines = preg_split('/\r\n|\r|\n/', $content);
        
        foreach ($lines as $line) {
            // Skip empty lines
            if (empty(trim($line))) continue;

            // Parse CSV line
            $data = str_getcsv($line);

            // Expecting Format: [Question, Answer, Explanation (Optional)]
            if (count($data) >= 2) {
                // Sanitize and ensure UTF-8 strings
                $q = trim($data[0]);
                $a = trim($data[1]);
                $e = isset($data[2]) ? trim($data[2]) : ''; // Explanation
                
                // Skip header row usually having "Question" or "Answer"
                if (strtolower($q) == 'question' && strtolower($a) == 'answer') continue;
                
                if (!empty($q) && !empty($a)) {
                    $key_points[] = [
                        'q' => sanitizeInput($q), 
                        'a' => sanitizeInput($a),
                        'e' => sanitizeInput($e)
                    ];
                }
            }
        }
    }

    // 2. Handle Manual Inputs (Merge with CSV data if any)
    $questions = $_POST['questions'] ?? [];
    $answers = $_POST['answers'] ?? [];
    $explanations = $_POST['explanations'] ?? [];
    
    for ($i = 0; $i < count($questions); $i++) {
        if (!empty(trim($questions[$i])) && !empty(trim($answers[$i]))) {
            $key_points[] = [
                'q' => sanitizeInput($questions[$i]),
                'a' => sanitizeInput($answers[$i]),
                'e' => isset($explanations[$i]) ? sanitizeInput($explanations[$i]) : ''
            ];
        }
    }
    
    if (empty($key_points)) {
        $message = "Error: Please add at least one Q&A pair via form or CSV.";
    } else {
        $json_points = json_encode($key_points);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO quick_revision (chapter_id, title, summary, key_points) VALUES (?, ?, ?, ?)");
            $stmt->execute([$chapter_id, $title, $summary, $json_points]);
            $message = "Quick Revision added successfully! (" . count($key_points) . " points)";
        } catch (PDOException $e) {
            $message = "Error: Database error - " . $e->getMessage();
        }
    }
}

// Get Classes for Initial Dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id")->fetchAll();
$all_subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
$all_chapters = $pdo->query("SELECT * FROM chapters ORDER BY chapter_order")->fetchAll();

// Get Revisions List
$revisions = $pdo->query("
    SELECT qr.*, ch.chapter_name, s.subject_name
    FROM quick_revision qr
    JOIN chapters ch ON qr.chapter_id = ch.chapter_id
    JOIN subjects s ON ch.subject_id = s.subject_id
    ORDER BY qr.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quick Revision - MCQ Admin</title>
    <style>
        /* Reusing Dashboard Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav ul { list-style: none; display: flex; gap: 5px; flex-wrap: wrap; }
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
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 15px; }

        /* Q&A Styles */
        .qa-container { margin-top: 15px; border: 1px solid #eee; padding: 15px; border-radius: 8px; }
        .qa-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: start; flex-wrap: wrap; }
        .qa-row input { flex: 1; min-width: 200px; }
        .btn-small { padding: 5px 10px; font-size: 12px; border-radius: 4px; border: none; cursor: pointer; }
        .btn-remove { background: #ff4444; color: white; height: 38px;}
        .btn-plus { background: #28a745; color: white; margin-top: 10px; }
        
        .csv-section { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #ccc; }
    </style>
    <script>
        // Pass PHP data to JS
        const subjects = <?php echo json_encode($all_subjects); ?>;
        const chapters = <?php echo json_encode($all_chapters); ?>;

        function filterSubjects() {
            const classId = document.getElementById('class_select').value;
            const subjectSelect = document.getElementById('subject_select');
            const chapterSelect = document.getElementById('chapter_select');
            
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

        function filterChapters() {
            const subjectId = document.getElementById('subject_select').value;
            const chapterSelect = document.getElementById('chapter_select');
            
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

        function addQuaRow() {
            const container = document.getElementById('qa_list');
            const div = document.createElement('div');
            div.className = 'qa-row';
            div.innerHTML = `
                <input type="text" name="questions[]" placeholder="Question" required>
                <input type="text" name="answers[]" placeholder="Answer" required>
                <input type="text" name="explanations[]" placeholder="Explanation (Optional)">
                <button type="button" class="btn-small btn-remove" onclick="this.parentElement.remove()">X</button>
            `;
            container.appendChild(div);
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
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php" class="active">Quick Revision</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <h2>Add Quick Revision</h2>
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Dropdowns -->
                    <select id="class_select" onchange="filterSubjects()" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="subject_select" onchange="filterChapters()" required>
                        <option value="">Select Subject (Choose Class First)</option>
                    </select>

                    <select name="chapter_id" id="chapter_select" required>
                        <option value="">Select Chapter (Choose Subject First)</option>
                    </select>

                    <input type="text" name="title" placeholder="Revision Title" required style="grid-column: span 3;">
                    
                    <textarea name="summary" placeholder="Chapter Summary..." style="grid-column: span 3; height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 8px;" required></textarea>
                </div>

                <div class="csv-section">
                    <h3>📂 Option 1: Upload CSV (Bulk Import)</h3>
                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">Format: <code>Question, Answer, Explanation</code> (3 Columns). Explanation is optional.</p>
                    <input type="file" name="csv_file" accept=".csv" style="background: white;">
                    <br><br>
                    <a href="sample_quick_revision_v2.csv" download style="font-size: 13px; color: #667eea;">⬇️ Download Sample CSV (Updated)</a>
                </div>

                <div class="qa-container">
                    <h3>⚡ Option 2: Manual Key Points (Q&A)</h3>
                    <div id="qa_list">
                        <div class="qa-row">
                            <input type="text" name="questions[]" placeholder="Question">
                            <input type="text" name="answers[]" placeholder="Answer">
                            <input type="text" name="explanations[]" placeholder="Explanation (Optional)">
                            <button type="button" class="btn-small btn-remove" onclick="this.parentElement.remove()">X</button>
                        </div>
                    </div>
                    <button type="button" class="btn-small btn-plus" onclick="addQuaRow()">+ Add Point</button>
                </div>

                <button type="submit" class="btn-add" style="margin-top: 20px; width: 100%;">Save Revision Content</button>
            </form>
        </div>

        <div class="card">
            <h2>Existing Revisions</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Chapter</th>
                        <th>Points</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($revisions as $rev): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($rev['title']); ?></td>
                        <td>
                            <small><?php echo htmlspecialchars($rev['subject_name']); ?></small><br>
                            <?php echo htmlspecialchars($rev['chapter_name']); ?>
                        </td>
                        <td>
                            <?php 
                                $points = json_decode($rev['key_points'], true);
                                echo is_array($points) ? count($points) : 0; 
                            ?> points
                        </td>
                        <td>
                            <a href="?delete=<?php echo $rev['revision_id']; ?>" class="btn-delete" onclick="return confirm('Delete this revision?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
