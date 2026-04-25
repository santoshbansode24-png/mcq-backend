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

// Check for Board Selection
if (!isset($_SESSION['admin_selected_board'])) {
    header('Location: select_board.php');
    exit();
}
$selected_board = $_SESSION['admin_selected_board'];
$board_name = $_SESSION['board_name'];

require_once '../config/db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Verify revision belongs to current board
    $check = $pdo->prepare("
        SELECT qr.revision_id FROM quick_revision qr
        JOIN chapters ch ON qr.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE qr.revision_id = ? AND c.board_type = ?
    ");
    $check->execute([$id, $selected_board]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM quick_revision WHERE revision_id = ?");
        $stmt->execute([$id]);
    }
    header('Location: quick_revision.php');
    exit();
}

// Handle Add Revision
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $chapter_id = intval($_POST['chapter_id']);
    // 0. Auto-generate Title from Chapter Name
    $stmtCh = $pdo->prepare("SELECT chapter_name FROM chapters WHERE chapter_id = ?");
    $stmtCh->execute([$chapter_id]);
    $title = $stmtCh->fetchColumn() . ' - Revision'; // e.g. "Algebra - Revision"
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

            // Expecting Format: [Question, Answer]
            if (count($data) >= 2) {
                // Sanitize and ensure UTF-8 strings
                $q = trim($data[0]);
                $a = trim($data[1]);
                $e = isset($data[2]) ? trim($data[2]) : ''; // Handle Explanation
                
                // Skip header row
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

    // 2. Handle Manual Inputs
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

// Get Revisions List
$revisions_query = $pdo->prepare("
    SELECT qr.*, ch.chapter_name, s.subject_name
    FROM quick_revision qr
    JOIN chapters ch ON qr.chapter_id = ch.chapter_id
    JOIN subjects s ON ch.subject_id = s.subject_id
    JOIN classes c ON s.class_id = c.class_id
    WHERE c.board_type = ?
    ORDER BY qr.created_at DESC
");
$revisions_query->execute([$selected_board]);
$revisions = $revisions_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quick Revision - MCQ Admin</title>
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=1777135263">
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
                <textarea name="explanations[]" placeholder="Explanation (Optional)"></textarea>
                <button type="button" class="btn-small btn-remove" onclick="this.parentElement.remove()">X</button>
            `;
            container.appendChild(div);
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
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php" class="active">Quick Revision</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
            <li><a href="ai_settings.php">🤖 AI Settings</a></li>
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
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="subject_select" onchange="filterChapters()" required>
                        <option value="">Select Subject (Choose Class First)</option>
                    </select>

                    <select name="chapter_id" id="chapter_select" required>
                        <option value="">Select Chapter (Choose Subject First)</option>
                    </select>


                    
                    <textarea name="summary" placeholder="Chapter Summary..." style="grid-column: span 3; height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 8px;" required></textarea>
                </div>

                <div class="csv-section">
                    <h3>📂 Option 1: Upload CSV (Bulk Import)</h3>
                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">Format: <code>Question, Answer, Explanation</code> (3 Columns). First row header ignored.</p>
                    <input type="file" name="csv_file" accept=".csv" style="background: white;">
                    <br><br>
                    <a href="sample_quick_revision.csv" download style="font-size: 13px; color: #667eea;">⬇️ Download Sample CSV</a>
                </div>

                <div class="qa-container">
                    <h3>⚡ Option 2: Manual Key Points (Q&A)</h3>
                    <div id="qa_list">
                        <div class="qa-row">
                            <input type="text" name="questions[]" placeholder="Question">
                            <input type="text" name="answers[]" placeholder="Answer">
                            <textarea name="explanations[]" placeholder="Explanation (Optional)"></textarea>
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
