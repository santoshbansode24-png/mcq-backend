<?php
/**
 * Flashcards Management with Bulk Upload
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
    header('Content-Disposition: attachment; filename="flashcards_sample.csv"');
    $output = fopen('php://output', 'w');
    // Simplified CSV: Just Question and Answer. Subject/Topic/Chapter are selected in UI.
    fputcsv($output, ['Question_Front', 'Answer_Back']);
    fputcsv($output, ['What is the capital of France?', 'Paris']);
    fputcsv($output, ['Define Photosynthesis', 'Process used by plants to convert light into energy']);
    fclose($output);
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM flashcards WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: flashcards.php');
    exit();
}

$message = '';
$messageType = ''; // success or error

function sanitizeTop($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Handle Single Add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_single') {
    $chapter_id = intval($_POST['chapter_id']);
    $front = sanitizeTop($_POST['question_front']);
    $back = sanitizeTop($_POST['answer_back']);
    
    // Fetch Subject/Topic names for legacy support/display (optional, or we just rely on joins)
    // For now, we will store simplified data or fetch properly.
    // The previous schema had 'subject' and 'topic' strings. We should ideally populate them or deprecate them.
    // Let's populate them from the DB for backward compatibility if needed, or just insert empty strings if we rely on chapter_id.
    // Best approach: Fetch subject name from chapter -> subject relation.
    
    try {
        // Get Subject Name
        $stmtS = $pdo->prepare("SELECT s.subject_name FROM chapters c JOIN subjects s ON c.subject_id = s.subject_id WHERE c.chapter_id = ?");
        $stmtS->execute([$chapter_id]);
        $subject = $stmtS->fetchColumn() ?: 'Unknown';
        
        $stmt = $pdo->prepare("INSERT INTO flashcards (chapter_id, subject, topic, question_front, answer_back) VALUES (?, ?, ?, ?, ?)");
        // Topic is effectively the Chapter Name or user defined. Let's use 'General' or blank for now as we didn't ask for a separate topic input.
        $topic = 'General'; 
        
        $stmt->execute([$chapter_id, $subject, $topic, $front, $back]);
        $message = "Flashcard added successfully!";
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
        
        // Skip header
        fgetcsv($handle);
        
        $count = 0;
        $errors = 0;
        
        // Get Subject Name
        $stmtS = $pdo->prepare("SELECT s.subject_name FROM chapters c JOIN subjects s ON c.subject_id = s.subject_id WHERE c.chapter_id = ?");
        $stmtS->execute([$chapter_id]);
        $subject = $stmtS->fetchColumn() ?: 'Unknown';
        $topic = 'General';

        $stmt = $pdo->prepare("INSERT INTO flashcards (chapter_id, subject, topic, question_front, answer_back) VALUES (?, ?, ?, ?, ?)");
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Need at least 2 columns now (Question, Answer)
            if (count($data) < 2) { $errors++; continue; }
            
            $front = sanitizeTop($data[0]);
            $back = sanitizeTop($data[1]);
            
            if (empty($front) || empty($back)) { $errors++; continue; }
            
            try {
                $stmt->execute([$chapter_id, $subject, $topic, $front, $back]);
                $count++;
            } catch (Exception $e) {
                $errors++;
            }
        }
        fclose($handle);
        
        $message = "Bulk upload complete! Added: $count Flashcards. Skipped/Errors: $errors.";
        $messageType = ($count > 0) ? "success" : "error";
    } else {
        $message = "Please upload a valid CSV file.";
        $messageType = "error";
    }
}

// Get Dropdown Data
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id")->fetchAll();
$all_subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
$all_chapters = $pdo->query("SELECT * FROM chapters ORDER BY chapter_order")->fetchAll();

// Get Flashcards List (Joined with Chapter/Subject for display)
$flashcards = $pdo->query("
    SELECT f.*, c.chapter_name, s.subject_name 
    FROM flashcards f 
    LEFT JOIN chapters c ON f.chapter_id = c.chapter_id 
    LEFT JOIN subjects s ON c.subject_id = s.subject_id 
    ORDER BY f.id DESC LIMIT 50
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Flashcards - MCQ Admin</title>
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
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php" class="active">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="tabs">
                <button class="tab-btn active" id="single-btn" onclick="switchTab('single')">Add Single Flashcard</button>
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
                        
                        <textarea name="question_front" placeholder="Question (Front Side)" required style="grid-column: span 2; height: 80px;"></textarea>
                        <textarea name="answer_back" placeholder="Answer (Back Side)" required style="grid-column: span 2; height: 80px;"></textarea>
                    </div>
                    <button type="submit" class="btn-add">Add Flashcard</button>
                </form>
            </div>

            <!-- Bulk Upload Form -->
            <div id="bulk-content" class="tab-content">
                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h3>📝 Instructions</h3>
                    <p style="margin: 10px 0; color: #666;">1. Download the sample CSV file.<br>2. Fill in the columns: Question_Front, Answer_Back.<br>3. Select Class, Subject, and Chapter.<br>4. Upload the file.</p>
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
                    <button type="submit" class="btn-add">Upload Flashcards</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Recent Flashcards</h2>
            <table>
                <thead>
                    <tr>
                        <th width="30%">Question</th>
                        <th>Answer</th>
                        <th>Chapter</th>
                        <th>Subject</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($flashcards) > 0): ?>
                        <?php foreach($flashcards as $fc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(substr($fc['question_front'], 0, 50)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars(substr($fc['answer_back'], 0, 50)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars($fc['chapter_name']); ?></td>
                            <td><?php echo htmlspecialchars($fc['subject_name'] ?? $fc['subject']); ?></td>
                            <td>
                                <a href="?delete=<?php echo $fc['id']; ?>" class="btn-delete" onclick="return confirm('Delete this card?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; color:#999;">No flashcards found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
