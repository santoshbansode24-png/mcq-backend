<?php
/**
 * Subjects Management
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
    // Verify subject belongs to current board
    $check = $pdo->prepare("
        SELECT s.subject_id FROM subjects s 
        JOIN classes c ON s.class_id = c.class_id 
        WHERE s.subject_id = ? AND c.board_type = ?
    ");
    $check->execute([$id, $selected_board]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
        $stmt->execute([$id]);
    }
    header('Location: subjects.php');
    exit();
}

// Handle Add Subject
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['subject_name']);
    $class_id = intval($_POST['class_id']);
    $desc = sanitizeInput($_POST['description']);
    
    // Normalize subject name to UPPERCASE
    $normalized_name = normalizeSubjectName($name);
    
    // Proper Duplicate Check
    $check_dup = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_name = ? AND class_id = ?");
    $check_dup->execute([$normalized_name, $class_id]);
    
    if ($check_dup->fetchColumn() > 0) {
        $error = "⚠️ Duplicate Subject: '$normalized_name' already exists in this class!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, class_id, description) VALUES (?, ?, ?)");
            $stmt->execute([$normalized_name, $class_id, $desc]);
            $message = "✓ Subject added successfully! ($normalized_name)";
        } catch (PDOException $e) {
            $error = "❌ Error: Database error occurred";
        }
    }
}

// Get Subjects (Filtered by Board)
$subjects_query = $pdo->prepare("
    SELECT s.*, c.class_name,
    (SELECT COUNT(*) FROM chapters WHERE subject_id = s.subject_id) as chapter_count
    FROM subjects s
    JOIN classes c ON s.class_id = c.class_id
    WHERE c.board_type = ?
    ORDER BY c.class_id, s.subject_name
");
$subjects_query->execute([$selected_board]);
$subjects = $subjects_query->fetchAll();

// Get Classes for dropdown (Filtered by Board)
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - MCQ Admin</title>
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
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
            <li><a href="subjects.php" class="active"><i class="fa-solid fa-book"></i> Subjects</a></li>
            <li><a href="chapters.php"><i class="fa-solid fa-file-lines"></i> Chapters</a></li>
            <li><a href="mcqs.php"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card" style="max-width: 600px;">
            <h2><i class="fa-solid fa-plus-circle"></i> Add New Subject</h2>
            <p style="margin-bottom: 15px; color: #666; font-size: 14px;">Adding to: <strong><?php echo $board_name; ?></strong></p>
            <?php if($message): ?><div class="alert success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert" style="background: #f8d7da; color: #721c24; border-color: #dc3545;"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-grid">
                    <select name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">Class <?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="subject_name" placeholder="Subject Name (e.g. Mathematics)" required>
                    <input type="text" name="description" placeholder="Description (Optional)" style="grid-column: span 2;">
                </div>
                <button type="submit" class="btn-add">Add Subject</button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-book"></i> All Subjects (<?php echo $board_name; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-layer-group"></i> Class</th>
                        <th><i class="fa-solid fa-tag"></i> Subject Name</th>
                        <th><i class="fa-solid fa-align-left"></i> Description</th>
                        <th><i class="fa-solid fa-file-lines"></i> Total Chapters</th>
                        <th><i class="fa-solid fa-bolt"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($subjects)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #666; padding: 20px;">No subjects found for this board.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($subjects as $subject): ?>
                        <tr>
                            <td><span style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px;">Class <?php echo htmlspecialchars($subject['class_name']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                            <td><small style="color: #666;"><?php echo htmlspecialchars($subject['description'] ?: '-'); ?></small></td>
                            <td><span style="background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px;"><?php echo $subject['chapter_count']; ?> chapters</span></td>
                            <td>
                                <a href="?delete=<?php echo $subject['subject_id']; ?>" class="btn-delete" onclick="return confirm('Delete this subject? All chapters and MCQs will be deleted!')"><i class="fa-solid fa-trash"></i> Delete</a>
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
