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
    
    // Check for duplicates
    if (isDuplicateSubject($pdo, $normalized_name, $class_id)) {
        $error = "⚠️ Duplicate Subject: A subject with this name already exists for the selected class!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, class_id, description) VALUES (?, ?, ?)");
            $stmt->execute([$normalized_name, $class_id, $desc]);
            $message = "✓ Subject added successfully! (Auto-capitalized to: $normalized_name)";
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
    <link rel="stylesheet" href="admin_theme.css?v=1777135263">
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
            <li><a href="subjects.php" class="active">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
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
            <h2>Add New Subject</h2>
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert" style="background: #f8d7da; color: #721c24; border-color: #dc3545;"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-grid">
                    <select name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="subject_name" placeholder="Subject Name (e.g. Mathematics)" required>
                    <input type="text" name="description" placeholder="Description (Optional)">
                </div>
                <button type="submit" class="btn-add">Add Subject</button>
            </form>
        </div>

        <div class="card">
            <h2>All Subjects (<?php echo $board_name; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Subject Name</th>
                        <th>Description</th>
                        <th>Chapters</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($subjects as $subject): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($subject['class_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                        <td><?php echo htmlspecialchars($subject['description']); ?></td>
                        <td><?php echo $subject['chapter_count']; ?> chapters</td>
                        <td>
                            <a href="?delete=<?php echo $subject['subject_id']; ?>" class="btn-delete" onclick="return confirm('Delete this subject? All chapters and MCQs will be deleted!')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
