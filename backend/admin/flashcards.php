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
    header('Content-Disposition: attachment; filename="flashcards_sample.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // Add BOM for Excel
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
    // Verify flashcard belongs to current board
    $check = $pdo->prepare("
        SELECT f.id FROM flashcards f 
        JOIN chapters c ON f.chapter_id = c.chapter_id 
        JOIN subjects s ON c.subject_id = s.subject_id 
        JOIN classes cls ON s.class_id = cls.class_id 
        WHERE f.id = ? AND cls.board_type = ?
    ");
    $check->execute([$id, $selected_board]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM flashcards WHERE id = ?");
        $stmt->execute([$id]);
    }
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
            
            // Need at least 2 columns now (Question, Answer)
            if (count($data) < 2) { 
                if ($errors == 0) { $first_error = "Row $row_num: Not enough columns (" . count($data) . "). Is it comma-separated?"; }
                $errors++; 
                continue; 
            }
            
            $front = sanitizeTop(convertUtf8($data[0]));
            $back = sanitizeTop(convertUtf8($data[1]));
            
            if (empty($front) || empty($back)) { 
                if ($errors == 0) { $first_error = "Row $row_num: Question or Answer is empty."; }
                $errors++; 
                continue; 
            }
            
            try {
                $stmt->execute([$chapter_id, $subject, $topic, $front, $back]);
                $count++;
            } catch (Exception $e) {
                if ($errors == 0) { $first_error = "Row $row_num DB Error: " . $e->getMessage(); }
                $errors++;
            }
        }
        fclose($handle);
        
        $message = "Bulk upload complete! Added: $count Flashcards. Skipped/Errors: $errors.";
        if (!empty($first_error)) {
            $message .= " Example error: " . $first_error;
        }
        $messageType = ($count > 0) ? "success" : "error";
    } else {
        $message = "Please upload a valid CSV file.";
        $messageType = "error";
    }
}

// Get Dropdown Data
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

// Get Flashcards List (Joined with Chapter/Subject for display)
$flashcards_query = $pdo->prepare("
    SELECT f.*, c.chapter_name, s.subject_name 
    FROM flashcards f 
    LEFT JOIN chapters c ON f.chapter_id = c.chapter_id 
    LEFT JOIN subjects s ON c.subject_id = s.subject_id 
    LEFT JOIN classes cls ON s.class_id = cls.class_id 
    WHERE cls.board_type = ?
    ORDER BY f.id DESC LIMIT 50
");
$flashcards_query->execute([$selected_board]);
$flashcards = $flashcards_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Flashcards - MCQ Admin</title>
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
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
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users</a></li>
            <li><a href="teachers.php"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a></li>
            <li><a href="classes.php"><i class="fa-solid fa-layer-group"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fa-solid fa-book"></i> Subjects</a></li>
            <li><a href="chapters.php"><i class="fa-solid fa-file-lines"></i> Chapters</a></li>
            <li><a href="mcqs.php"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php" class="active"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <div class="tabs">
                <button class="tab-btn active" id="bulk-btn" onclick="switchTab('bulk')">Bulk Upload (CSV)</button>
                <button class="tab-btn" id="single-btn" onclick="switchTab('single')">Add Single Flashcard</button>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Single Add Form -->
            <div id="single-content" class="tab-content">
                <form method="POST">
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
                        
                        <textarea name="question_front" placeholder="Question (Front Side)" required style="grid-column: span 2; height: 80px;"></textarea>
                        <textarea name="answer_back" placeholder="Answer (Back Side)" required style="grid-column: span 2; height: 80px;"></textarea>
                    </div>
                    <button type="submit" class="btn-add">Add Flashcard</button>
                </form>
            </div>

            <!-- Bulk Upload Form -->
            <div id="bulk-content" class="tab-content active">
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
                    <button type="submit" class="btn-add">Upload Flashcards</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-bolt"></i> Recent Flashcards (<?php echo $board_name; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th width="30%"><i class="fa-solid fa-question-circle"></i> Question</th>
                        <th><i class="fa-solid fa-comment-dots"></i> Answer</th>
                        <th><i class="fa-solid fa-file-lines"></i> Chapter</th>
                        <th><i class="fa-solid fa-book"></i> Subject</th>
                        <th><i class="fa-solid fa-bolt"></i> Action</th>
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
