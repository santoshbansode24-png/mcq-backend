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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['subject_name']);
    $class_id = intval($_POST['class_id']);
    $desc = sanitizeInput($_POST['description']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, class_id, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $class_id, $desc]);
        $message = "Subject added successfully!";
    } catch (PDOException $e) {
        $message = "Error: Database error";
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
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 15px; }
        
        /* Header Info */
        .header-right { display: flex; align-items: center; gap: 20px; }
        .admin-info { text-align: right; }
        .admin-info .name { font-weight: 600; font-size: 15px; }
        .admin-info .email { font-size: 13px; opacity: 0.9; }
        .btn-logout { background: rgba(255,255,255,0.2); color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 13px; }
        
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
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <h2>Add New Subject</h2>
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
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
