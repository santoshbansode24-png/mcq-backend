<?php
/**
 * Chapters Management
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
require_once '../helpers/text_normalizer.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Verify chapter belongs to current board
    $check = $pdo->prepare("
        SELECT ch.chapter_id FROM chapters ch 
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id 
        WHERE ch.chapter_id = ? AND c.board_type = ?
    ");
    $check->execute([$id, $selected_board]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM chapters WHERE chapter_id = ?");
        $stmt->execute([$id]);
    }
    header('Location: chapters.php');
    exit();
}

// Handle Add Chapter
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = intval($_POST['subject_id']);
    $name = sanitizeInput($_POST['chapter_name']);

    $desc = sanitizeInput($_POST['description']);
    $order = intval($_POST['chapter_order']);
    
    // Normalize chapter name to UPPERCASE
    $normalized_name = normalizeChapterName($name);
    
    // Proper Duplicate Check
    $check_dup = $pdo->prepare("SELECT COUNT(*) FROM chapters WHERE chapter_name = ? AND subject_id = ?");
    $check_dup->execute([$normalized_name, $subject_id]);
    
    if ($check_dup->fetchColumn() > 0) {
        $error = "⚠️ Duplicate Chapter: '$normalized_name' already exists in this subject!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description, chapter_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$subject_id, $normalized_name, $desc, $order]);
            $message = "✓ Chapter added successfully! ($normalized_name)";
        } catch (PDOException $e) {
            $error = "❌ Error: Database error occurred";
        }
    }
}

// Get Classes for Initial Dropdown
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();

// Get All Subjects (for JS filtering)
$all_subjects_query = $pdo->prepare("
    SELECT s.* FROM subjects s 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE c.board_type = ? 
    ORDER BY s.subject_name
");
$all_subjects_query->execute([$selected_board]);
$all_subjects = $all_subjects_query->fetchAll();

// Get Chapters List
$chapters_query = $pdo->prepare("
    SELECT ch.*, s.subject_name, c.class_name,
    (SELECT COUNT(*) FROM mcqs WHERE chapter_id = ch.chapter_id) as mcq_count,
    (SELECT COUNT(*) FROM videos WHERE chapter_id = ch.chapter_id) as video_count
    FROM chapters ch
    JOIN subjects s ON ch.subject_id = s.subject_id
    JOIN classes c ON s.class_id = c.class_id
    WHERE c.board_type = ?
    ORDER BY c.class_id, s.subject_name, ch.chapter_order
");
$chapters_query->execute([$selected_board]);
$chapters = $chapters_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Chapters - MCQ Admin</title>
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
    <script>
        const subjects = <?php echo json_encode($all_subjects); ?>;
        function filterSubjects() {
            const classId = document.getElementById('class_select').value;
            const subjectSelect = document.getElementById('subject_select');
            
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            
            subjects.forEach(subject => {
                if (subject.class_id == classId) {
                    let option = document.createElement('option');
                    option.value = subject.subject_id;
                    option.textContent = subject.subject_name;
                    subjectSelect.appendChild(option);
                }
            });
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>🎓 MCQ Admin Panel</h1>
        
        <div class="center-actions">
            <a href="select_board.php" class="btn-switch-board">🔁 Switch Board</a>
        </div>

        <div class="header-right">
            <div class="admin-info">
                <div class="name">
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
            <li><a href="chapters.php" class="active"><i class="fa-solid fa-file-lines"></i> Chapters</a></li>
            <li><a href="mcqs.php"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="audit_center.php"><i class="fa-solid fa-clipboard-check"></i> Audit Center</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card" style="max-width: 600px;">
            <h2><i class="fa-solid fa-plus-circle"></i> Add New Chapter</h2>
            <p style="margin-bottom: 15px; color: #666; font-size: 14px;">Adding to: <strong><?php echo $board_name; ?></strong></p>
            <?php if($message): ?><div class="alert success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert" style="background: #f8d7da; color: #721c24; border-color: #dc3545;"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-grid">
                    <select id="class_select" onchange="filterSubjects()" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">Class <?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="subject_id" id="subject_select" required>
                        <option value="">Select Subject (Choose Class First)</option>
                    </select>
                    
                    <input type="text" name="chapter_name" placeholder="Chapter Name" style="grid-column: span 2;" required>
                    <input type="text" name="description" placeholder="Description (Optional)" style="grid-column: span 2;">
                    
                    <input type="number" name="chapter_order" placeholder="Chapter Order (e.g. 1)" value="1" required style="max-width: 200px;">
                </div>
                <button type="submit" class="btn-add" style="margin-top: 15px;">Add Chapter</button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-file-lines"></i> All Chapters (<?php echo $board_name; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-layer-group"></i> Class</th>
                        <th><i class="fa-solid fa-book"></i> Subject</th>
                        <th><i class="fa-solid fa-hashtag"></i> No.</th>
                        <th><i class="fa-solid fa-tag"></i> Chapter Name</th>
                        <th><i class="fa-solid fa-chart-bar"></i> Content Stats</th>
                        <th><i class="fa-solid fa-bolt"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($chapters)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666; padding: 20px;">No chapters found for this board.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($chapters as $chapter): ?>
                        <tr>
                            <td><span style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px;">Class <?php echo htmlspecialchars($chapter['class_name']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($chapter['subject_name']); ?></strong></td>
                            <td><span style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?php echo $chapter['chapter_order']; ?></span></td>
                            <td>
                                <strong><?php echo htmlspecialchars($chapter['chapter_name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($chapter['description'] ?: ''); ?></small>
                            </td>
                            <td>
                                <div style="display: flex; gap: 10px; font-size: 12px;">
                                    <span style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 12px;"><?php echo $chapter['mcq_count']; ?> MCQs</span>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 12px;"><?php echo $chapter['video_count']; ?> Videos</span>
                                </div>
                            </td>
                            <td>
                                <a href="?delete=<?php echo $chapter['chapter_id']; ?>" class="btn-delete" onclick="return confirm('Delete this chapter? All MCQs and contents will be deleted!')"><i class="fa-solid fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
