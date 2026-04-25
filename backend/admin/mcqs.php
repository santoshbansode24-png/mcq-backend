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
        
        $medium = isset($_POST['medium']) ? $_POST['medium'] : 'english';
        
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty, medium) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $row_num = 1; // Header is row 1
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
                $errors++; 
                continue; 
            }
            
            $question = sanitizeInput(convertUtf8($data[0]));
            if (empty($question)) {
                if ($errors == 0) { $first_error = "Row $row_num: Question is empty."; }
                $errors++;
                continue;
            }

            $opt_a = sanitizeInput(convertUtf8($data[1]));
            $opt_b = sanitizeInput(convertUtf8($data[2]));
            $opt_c = sanitizeInput(convertUtf8($data[3]));
            $opt_d = sanitizeInput(convertUtf8($data[4]));
            
            // Clean up correct answer (allow 'Option A' or 'A')
            $correct = strtolower(trim(convertUtf8($data[5]))); // a, b, c, d
            $correct = str_replace(['option ', 'option'], '', $correct);
            
            $explanation = isset($data[6]) ? sanitizeInput(convertUtf8($data[6])) : '';
            
            $diff_input = isset($data[7]) ? strtolower(trim(convertUtf8($data[7]))) : '';
            $difficulty = empty($diff_input) ? 'medium' : $diff_input;
            if (!in_array($difficulty, ['easy', 'medium', 'hard'])) { $difficulty = 'medium'; }
            
            // Image Upload is removed for Bulk CSV
            $image_subpath = null;
            
            // Validate correct answer format
            if (!in_array($correct, ['a', 'b', 'c', 'd'])) { 
                if ($errors == 0) { $first_error = "Row $row_num: Invalid correct answer '$correct'"; }
                $errors++; 
                continue; 
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
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=1777115478">
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

                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h3>Upload CSV 📝</h3>
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
