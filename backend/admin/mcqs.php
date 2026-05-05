<?php
/**
 * MCQs Management with Bulk Upload & Search
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
    fclose($output);
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $check = $pdo->prepare("SELECT m.mcq_id FROM mcqs m JOIN chapters ch ON m.chapter_id = ch.chapter_id JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE m.mcq_id = ? AND c.board_type = ?");
    $check->execute([$id, $selected_board]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM mcqs WHERE mcq_id = ?");
        $stmt->execute([$id]);
    }
    header('Location: mcqs.php');
    exit();
}

$message = ''; $messageType = '';

// Handle Single Add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_single') {
    $chapter_id = intval($_POST['chapter_id']);
    $question = sanitizeInput($_POST['question']);
    $opt_a = sanitizeInput($_POST['option_a']); $opt_b = sanitizeInput($_POST['option_b']);
    $opt_c = sanitizeInput($_POST['option_c']); $opt_d = sanitizeInput($_POST['option_d']);
    $correct = $_POST['correct_answer'];
    $explanation = sanitizeInput($_POST['explanation']);
    $difficulty = $_POST['difficulty'];
    try {
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$chapter_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $difficulty]);
        $message = "✓ MCQ added successfully!"; $messageType = "success";
    } catch (PDOException $e) { $message = "❌ Error: " . $e->getMessage(); $messageType = "error"; }
}

// Handle Bulk Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_upload') {
    $chapter_id = intval($_POST['chapter_id']);
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        fgetcsv($handle); // Skip header
        $count = 0; $errors = 0;
        $stmt = $pdo->prepare("INSERT INTO mcqs (chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) < 6) { $errors++; continue; }
            try {
                $stmt->execute([$chapter_id, sanitizeInput($data[0]), sanitizeInput($data[1]), sanitizeInput($data[2]), sanitizeInput($data[3]), sanitizeInput($data[4]), strtolower(trim($data[5])), sanitizeInput($data[6] ?? ''), strtolower(trim($data[7] ?? 'medium'))]);
                $count++;
            } catch (Exception $e) { $errors++; }
        }
        fclose($handle);
        $message = "✓ Uploaded: $count. Errors: $errors."; $messageType = "success";
    }
}

// Get Data for Selects
$classes = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes->execute([$selected_board]); $classes = $classes->fetchAll();
$all_subjects = $pdo->prepare("SELECT s.* FROM subjects s JOIN classes c ON s.class_id = c.class_id WHERE c.board_type = ? ORDER BY s.subject_name");
$all_subjects->execute([$selected_board]); $all_subjects = $all_subjects->fetchAll();
$all_chapters = $pdo->prepare("SELECT ch.* FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE c.board_type = ? ORDER BY ch.chapter_order");
$all_chapters->execute([$selected_board]); $all_chapters = $all_chapters->fetchAll();

// Filtering Logic
$search = $_GET['search'] ?? ''; $f_sub = $_GET['f_subject'] ?? '';
$q = "SELECT m.*, ch.chapter_name, s.subject_name FROM mcqs m JOIN chapters ch ON m.chapter_id = ch.chapter_id JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE c.board_type = ?";
$p = [$selected_board];
if($f_sub){ $q .= " AND s.subject_id = ?"; $p[] = $f_sub; }
if($search){ $q .= " AND m.question LIKE ?"; $p[] = "%$search%"; }
$q .= " ORDER BY m.mcq_id DESC LIMIT 100";
$mcqs_q = $pdo->prepare($q); $mcqs_q->execute($p); $mcqs = $mcqs_q->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCQ Management - Veeru</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
    <script>
        const subjects = <?php echo json_encode($all_subjects); ?>;
        const chapters = <?php echo json_encode($all_chapters); ?>;
        function filterSubjects(p) {
            const cid = document.getElementById(p+'class_select').value;
            const ssel = document.getElementById(p+'subject_select');
            const csel = document.getElementById(p+'chapter_select');
            ssel.innerHTML = '<option value="">Select Subject</option>';
            csel.innerHTML = '<option value="">Select Chapter</option>';
            subjects.forEach(s => { if(s.class_id == cid){ let o = document.createElement('option'); o.value=s.subject_id; o.textContent=s.subject_name; ssel.appendChild(o); }});
        }
        function filterChapters(p) {
            const sid = document.getElementById(p+'subject_select').value;
            const csel = document.getElementById(p+'chapter_select');
            csel.innerHTML = '<option value="">Select Chapter</option>';
            chapters.forEach(c => { if(c.subject_id == sid){ let o = document.createElement('option'); o.value=c.chapter_id; o.textContent=c.chapter_name; csel.appendChild(o); }});
        }
        function switchTab(t) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(t+'-content').classList.add('active');
            document.getElementById(t+'-btn').classList.add('active');
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>🎓 📝 MCQs Management</h1>
        <div class="header-right">
            <div class="admin-info">
                <div class="name"><span><?php echo $board_name; ?></span> <?php echo $_SESSION['admin_name']; ?></div>
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
            <li><a href="mcqs.php" class="active"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="card">
            <div class="tabs">
                <button class="tab-btn active" id="bulk-btn" onclick="switchTab('bulk')">Bulk Upload</button>
                <button class="tab-btn" id="single-btn" onclick="switchTab('single')">Add Single</button>
            </div>
            <?php if($message): ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div><?php endif; ?>
            
            <div id="bulk-content" class="tab-content active">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="bulk_upload">
                    <div class="form-grid">
                        <select id="bulk_class_select" onchange="filterSubjects('bulk_')" required><option value="">Select Class</option><?php foreach($classes as $c): ?><option value="<?php echo $c['class_id']; ?>"><?php echo $c['class_name']; ?></option><?php endforeach; ?></select>
                        <select id="bulk_subject_select" onchange="filterChapters('bulk_')" required><option value="">Select Subject</option></select>
                        <select name="chapter_id" id="bulk_chapter_select" required><option value="">Select Chapter</option></select>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn-add">Upload CSV</button>
                </form>
            </div>

            <div id="single-content" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_single">
                    <div class="form-grid">
                        <select id="single_class_select" onchange="filterSubjects('single_')" required><option value="">Select Class</option><?php foreach($classes as $c): ?><option value="<?php echo $c['class_id']; ?>"><?php echo $c['class_name']; ?></option><?php endforeach; ?></select>
                        <select id="single_subject_select" onchange="filterChapters('single_')" required><option value="">Select Subject</option></select>
                        <select name="chapter_id" id="single_chapter_select" required><option value="">Select Chapter</option></select>
                        <textarea name="question" placeholder="Question" required style="grid-column: span 2; height: 80px;"></textarea>
                        <input type="text" name="option_a" placeholder="A" required><input type="text" name="option_b" placeholder="B" required>
                        <input type="text" name="option_c" placeholder="C" required><input type="text" name="option_d" placeholder="D" required>
                        <select name="correct_answer" required><option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option></select>
                        <select name="difficulty"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select>
                    </div>
                    <button type="submit" class="btn-add">Add MCQ</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>MCQ Database</h2>
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" style="width: 200px; margin-bottom: 0;">
                    <select name="f_subject" style="margin-bottom: 0;">
                        <option value="">All Subjects</option>
                        <?php foreach($all_subjects as $s): ?><option value="<?php echo $s['subject_id']; ?>" <?php echo $f_sub == $s['subject_id'] ? 'selected' : ''; ?>><?php echo $s['subject_name']; ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-add" style="margin: 0;"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
            </div>
            <table>
                <thead><tr><th>Question</th><th>Chapter</th><th>Ans</th><th>Difficulty</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach($mcqs as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($m['question'], 0, 60)) . '...'; ?></td>
                        <td><small><?php echo $m['subject_name']; ?></small><br><?php echo $m['chapter_name']; ?></td>
                        <td><strong><?php echo strtoupper($m['correct_answer']); ?></strong></td>
                        <td><?php echo ucfirst($m['difficulty']); ?></td>
                        <td><a href="?delete=<?php echo $m['mcq_id']; ?>" class="btn-delete" onclick="return confirm('Delete?')">Delete</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
