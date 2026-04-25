<?php
/**
 * Classes Management
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
    // Verify class belongs to current board before deleting
    $check = $pdo->prepare("SELECT board_type FROM classes WHERE class_id = ?");
    $check->execute([$id]);
    $res = $check->fetch();
    
    if ($res && $res['board_type'] == $selected_board) {
        $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id = ?");
        $stmt->execute([$id]);
    }
    header('Location: classes.php');
    exit();
}

// Handle Add Class
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['class_name']);
    // Board is now fixed from session
    $board = $selected_board; 
    
    // Normalize class name to UPPERCASE
    $normalized_name = normalizeClassName($name);
    
    // Check for duplicates
    if (isDuplicateClass($pdo, $normalized_name, 1, null)) { // Assuming board_id = 1 for now
        $error = "⚠️ Duplicate Class: A class with this name already exists for this board!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO classes (class_name, board_type) VALUES (?, ?)");
            $stmt->execute([$normalized_name, $board]);
            $message = "✓ Class added successfully! (Auto-capitalized to: $normalized_name)";
        } catch (PDOException $e) {
            $error = "❌ Error: Database error occurred";
        }
    }
}

// Get Classes (Filtered by Board)
$classes = $pdo->prepare("
    SELECT c.*, 
    (SELECT COUNT(*) FROM users WHERE class_id = c.class_id) as student_count,
    (SELECT COUNT(*) FROM subjects WHERE class_id = c.class_id) as subject_count
    FROM classes c 
    WHERE board_type = ?
    ORDER BY class_id
");
$classes->execute([$selected_board]);
$classes = $classes->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - MCQ Admin</title>
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=1777115478">
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
            <li><a href="classes.php" class="active">Classes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
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
        <div class="card" style="max-width: 500px;">
            <h2>Add New Class</h2>
            <p style="margin-bottom: 15px; color: #666; font-size: 14px;">Adding to: <strong><?php echo $board_name; ?></strong></p>
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert" style="background: #f8d7da; color: #721c24; border-color: #dc3545;"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <input type="text" name="class_name" placeholder="Class Name (e.g. Class 10)" required>
                <!-- Board Type is Hidden/Fixed -->
                <button type="submit" class="btn-add">Add Class</button>
            </form>
        </div>

        <div class="card">
            <h2>All Classes (<?php echo $board_name; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Total Students</th>
                        <th>Total Subjects</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($classes as $class): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                        <td><?php echo $class['student_count']; ?> students</td>
                        <td><?php echo $class['subject_count']; ?> subjects</td>
                        <td>
                            <a href="?delete=<?php echo $class['class_id']; ?>" class="btn-delete" onclick="return confirm('Delete this class? All students and subjects in this class will be deleted!')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
