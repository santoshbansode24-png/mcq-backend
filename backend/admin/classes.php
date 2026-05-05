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
    $board = $selected_board; 
    
    // Normalize class name to UPPERCASE
    $normalized_name = normalizeClassName($name);
    
    // Proper Board-Specific Duplicate Check
    $check_dup = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_name = ? AND board_type = ?");
    $check_dup->execute([$normalized_name, $board]);
    
    if ($check_dup->fetchColumn() > 0) {
        $error = "⚠️ Duplicate Class: '$normalized_name' already exists in $board_name!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO classes (class_name, board_type) VALUES (?, ?)");
            $stmt->execute([$normalized_name, $board]);
            $message = "✓ Class added successfully! ($normalized_name)";
        } catch (PDOException $e) {
            $error = "❌ Error: Database error occurred";
        }
    }
}

// Get Classes (Filtered by Board)
$classes = $pdo->prepare("
    SELECT c.*, 
    (SELECT COUNT(*) FROM users WHERE class_id = c.class_id AND user_type = 'student') as student_count,
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
            <li><a href="classes.php" class="active"><i class="fa-solid fa-layer-group"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fa-solid fa-book"></i> Subjects</a></li>
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
        <div class="card" style="max-width: 500px;">
            <h2><i class="fa-solid fa-plus-circle"></i> Add New Class</h2>
            <p style="margin-bottom: 15px; color: #666; font-size: 14px;">Adding to: <strong><?php echo $board_name; ?></strong></p>
            <?php if($message): ?><div class="alert success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert" style="background: #f8d7da; color: #721c24; border-color: #dc3545;"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <input type="text" name="class_name" placeholder="Class Name (e.g. Class 10)" required>
                <button type="submit" class="btn-add">Add Class</button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-layer-group"></i> All Classes (<?php echo $board_name; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-tag"></i> Class Name</th>
                        <th><i class="fa-solid fa-users"></i> Total Students</th>
                        <th><i class="fa-solid fa-book"></i> Total Subjects</th>
                        <th><i class="fa-solid fa-bolt"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #666; padding: 20px;">No classes found for this board.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($classes as $class): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                            <td><span style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px;"><?php echo $class['student_count']; ?> students</span></td>
                            <td><span style="background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px;"><?php echo $class['subject_count']; ?> subjects</span></td>
                            <td>
                                <a href="?delete=<?php echo $class['class_id']; ?>" class="btn-delete" onclick="return confirm('Delete this class? All students and subjects in this class will be deleted!')"><i class="fa-solid fa-trash"></i> Delete</a>
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
