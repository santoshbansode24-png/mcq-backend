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
require_once '../config/db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
    $stmt->execute([$id]);
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

// Get Subjects
$subjects = $pdo->query("
    SELECT s.*, c.class_name,
    (SELECT COUNT(*) FROM chapters WHERE subject_id = s.subject_id) as chapter_count
    FROM subjects s
    JOIN classes c ON s.class_id = c.class_id
    ORDER BY c.class_id, s.subject_name
")->fetchAll();

// Get Classes for dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id")->fetchAll();
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
    </style>
</head>
<body>
    <div class="header">
        <h1>🎓 MCQ Admin Panel</h1>
        <a href="logout.php" style="color: white; text-decoration: none;">Logout</a>
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
                            <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="subject_name" placeholder="Subject Name (e.g. Mathematics)" required>
                    <input type="text" name="description" placeholder="Description (Optional)">
                </div>
                <button type="submit" class="btn-add">Add Subject</button>
            </form>
        </div>

        <div class="card">
            <h2>All Subjects</h2>
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
