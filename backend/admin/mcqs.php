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

// Handle Sample CSV Download
if (isset($_GET['action']) && $_GET['action'] == 'download_sample') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="mcq_sample.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer (a/b/c/d)', 'Explanation', 'Difficulty (easy/medium/hard)', 'Image File (e.g. q1.jpg)']);
    fputcsv($output, ['What is 2+2?', '3', '4', '5', '6', 'b', '2 plus 2 equals 4', 'easy', '']);
    fputcsv($output, ['Identify this shape', 'Circle', 'Square', 'Triangle', 'Rectangle', 'c', 'It has 3 sides', 'medium', 'triangle.jpg']);
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
    
    // Handle Image Upload
    $image_url = null;
    if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] == 0) {
        $upload_dir = '../uploads/mcq_images/';
        if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
        
        $file_ext = strtolower(pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_name = uniqid('mcq_') . '.' . $file_ext;
            if (hash_file('sha256', $_FILES['question_image']['tmp_name']) === false) {
                 $message = "Error: Invalid file content.";
                 $messageType = "error";
            } else {
                 if (move_uploaded_file($_FILES['question_image']['tmp_name'], $upload_dir . $new_name)) {
                     $image_url = 'mcq_images/' . $new_name;
                 }
            }
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$chapter_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $difficulty, $image_url]);
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
        
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Validate row has enough columns (at least 6)
            if (count($data) < 6) { $errors++; continue; }
            
            $question = sanitizeInput(convertUtf8($data[0]));
            $opt_a = sanitizeInput(convertUtf8($data[1]));
            $opt_b = sanitizeInput(convertUtf8($data[2]));
            $opt_c = sanitizeInput(convertUtf8($data[3]));
            $opt_d = sanitizeInput(convertUtf8($data[4]));
            $correct = strtolower(trim(convertUtf8($data[5]))); // a, b, c, d
            $explanation = isset($data[6]) ? sanitizeInput(convertUtf8($data[6])) : '';
            $difficulty = isset($data[7]) ? strtolower(trim(convertUtf8($data[7]))) : 'medium';
            // Column 9 (Index 8) is Image Filename
            $image_filename = isset($data[8]) ? trim(convertUtf8($data[8])) : null;
            $image_subpath = null;
            
            // If filename provided, check if it exists in uploads/mcq_images/
            if ($image_filename) {
                // Security check: Only allow basename to prevent directory traversal
                $clean_name = basename($image_filename);
                if (file_exists('../uploads/mcq_images/' . $clean_name)) {
                    $image_subpath = 'mcq_images/' . $clean_name;
                }
            }
            
            // Validate correct answer format
            if (!in_array($correct, ['a', 'b', 'c', 'd'])) { $errors++; continue; }
            
            try {
                $stmt->execute([$chapter_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $difficulty, $image_subpath]);
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
// Handle Bulk Image Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_image_upload') {
    $upload_dir = '../uploads/mcq_images/';
    if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }
    
    $count = 0;
    $errors = 0;
    
    if (isset($_FILES['image_files']) && is_array($_FILES['image_files']['name'])) {
        foreach ($_FILES['image_files']['name'] as $i => $name) {
            if ($_FILES['image_files']['error'][$i] == 0) {
                $tmp_name = $_FILES['image_files']['tmp_name'][$i];
                // Use original filename so CSV mapping works
                // Sanitize filename to prevent issues
                $clean_name = basename($name); // e.g., "Figure_Page1.png"
                if (move_uploaded_file($tmp_name, $upload_dir . $clean_name)) {
                    $count++;
                } else {
                    $errors++;
                }
            } else {
                $errors++;
            }
        }
        $message = "Images Uploaded: $count. Errors: $errors. Now you can upload the CSV.";
        $messageType = ($count > 0) ? "success" : "error";
    }
}

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
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .btn-download { background: #28a745; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; display: inline-block; margin-bottom: 15px; font-size: 14px; }
        
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
            
            document.getElementById(tabName + '-content').classList.add('active');
            document.getElementById(tabName + '-btn').classList.add('active');
        }
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
            <li><a href="ai_settings.php">🤖 AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="tabs">
                <button class="tab-btn active" id="bulk-btn" onclick="switchTab('bulk')">Bulk Upload (CSV)</button>
                <button class="tab-btn" id="single-btn" onclick="switchTab('single')">Add Single MCQ</button>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

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

                        <div style="grid-column: span 2; background: #fff; padding: 10px; border: 1px dashed #ccc; border-radius: 8px;">
                            <label style="display:block; margin-bottom:5px; font-weight:600;">Upload Diagram/Image (Optional):</label>
                            <input type="file" name="question_image" accept="image/*">
                            <small style="color: #666; display: block; margin-top: 5px;">Supported: JPG, PNG. Max 2MB.</small>
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
                        
                        <textarea name="explanation" placeholder="Explanation (Optional)" style="grid-column: span 2;"></textarea>
                    </div>
                    <button type="submit" class="btn-add">Add MCQ</button>
                </form>
            </div>

            <!-- Bulk Upload Form -->
            <div id="bulk-content" class="tab-content active">
                <!-- STEP 1: BULK IMAGES -->
                <div style="margin-bottom: 25px; padding: 20px; background: #e0f2fe; border-radius: 10px; border: 1px solid #bae6fd;">
                    <h3 style="color: #0369a1; margin-bottom: 10px;">Step 1: Upload Images (Optional) 📸</h3>
                    <p style="color: #0c4a6e; font-size: 14px; margin-bottom: 15px;">
                        If your CSV refers to images (e.g., "q1.png"), upload those files here <b>FIRST</b>.<br>
                        You can select multiple files at once.
                    </p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="bulk_image_upload">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="file" name="image_files[]" multiple accept="image/*" required style="padding: 10px; background: white; border-radius: 6px; border: 1px solid #cbd5e1; flex: 1;">
                            <button type="submit" class="btn-add" style="background: #0ea5e9;">Upload Images</button>
                        </div>
                    </form>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h3>Step 2: Upload CSV 📝</h3>
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
                        <td>
                            <?php if($mcq['image_url']): ?>
                                <div style="margin-bottom:5px; color:#667eea; font-size:12px;">[ Image Attached ]</div>
                            <?php endif; ?>
                            <?php echo htmlspecialchars(substr($mcq['question'], 0, 50)) . '...'; ?>
                        </td>
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
