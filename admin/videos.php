<?php
/**
 * Videos Management
 * MCQ Project 2.0
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
    $stmt = $pdo->prepare("DELETE FROM videos WHERE video_id = ?");
    $stmt->execute([$id]);
    header('Location: videos.php');
    exit();
}

// Handle Add Video
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $chapter_id = intval($_POST['chapter_id']);
    $title = sanitizeInput($_POST['title']);
    $url = sanitizeInput($_POST['url']);
    $desc = sanitizeInput($_POST['description']);
    $duration = sanitizeInput($_POST['duration']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO videos (chapter_id, title, url, description, duration) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$chapter_id, $title, $url, $desc, $duration]);
        $message = "Video added successfully!";
    } catch (PDOException $e) {
        $message = "Error: Database error";
    }
}

// Get Classes for Initial Dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id")->fetchAll();

// Get All Subjects (for JS filtering)
$all_subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();

// Get All Chapters (for JS filtering)
$all_chapters = $pdo->query("SELECT * FROM chapters ORDER BY chapter_order")->fetchAll();

// Get Videos List
$videos = $pdo->query("
    SELECT v.*, ch.chapter_name, s.subject_name
    FROM videos v
    JOIN chapters ch ON v.chapter_id = ch.chapter_id
    JOIN subjects s ON ch.subject_id = s.subject_id
    ORDER BY v.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Videos - MCQ Admin</title>
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
    <script>
        // Pass PHP data to JS
        const subjects = <?php echo json_encode($all_subjects); ?>;
        const chapters = <?php echo json_encode($all_chapters); ?>;

        function filterSubjects() {
            const classId = document.getElementById('class_select').value;
            const subjectSelect = document.getElementById('subject_select');
            const chapterSelect = document.getElementById('chapter_select');
            
            // Clear dropdowns
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            chapterSelect.innerHTML = '<option value="">Select Chapter (Choose Subject First)</option>';
            
            // Filter Subjects
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
            
            // Clear dropdown
            chapterSelect.innerHTML = '<option value="">Select Chapter</option>';
            
            // Filter Chapters
            chapters.forEach(chapter => {
                if (chapter.subject_id == subjectId) {
                    const option = document.createElement('option');
                    option.value = chapter.chapter_id;
                    option.textContent = chapter.chapter_name;
                    chapterSelect.appendChild(option);
                }
            });
        }
    </script>
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
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php" class="active">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <h2>Add New Video</h2>
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-grid">
                    <!-- Step 1: Select Class -->
                    <select id="class_select" onchange="filterSubjects()" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Step 2: Select Subject -->
                    <select id="subject_select" onchange="filterChapters()" required>
                        <option value="">Select Subject (Choose Class First)</option>
                    </select>

                    <!-- Step 3: Select Chapter -->
                    <select name="chapter_id" id="chapter_select" required>
                        <option value="">Select Chapter (Choose Subject First)</option>
                    </select>

                    <input type="text" name="title" placeholder="Video Title" required>
                    <input type="url" name="url" placeholder="Video URL (YouTube link)" required>
                    <input type="text" name="duration" placeholder="Duration (e.g. 10:30)">
                    <textarea name="description" placeholder="Description (Optional)" style="grid-column: span 2;"></textarea>
                </div>
                <button type="submit" class="btn-add">Add Video</button>
            </form>
        </div>

        <div class="card">
            <h2>All Videos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Chapter</th>
                        <th>URL</th>
                        <th>Duration</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($videos as $video): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($video['title']); ?></td>
                        <td>
                            <small><?php echo htmlspecialchars($video['subject_name']); ?></small><br>
                            <?php echo htmlspecialchars($video['chapter_name']); ?>
                        </td>
                        <td><a href="<?php echo htmlspecialchars($video['url']); ?>" target="_blank">Watch</a></td>
                        <td><?php echo htmlspecialchars($video['duration']); ?></td>
                        <td>
                            <a href="?delete=<?php echo $video['video_id']; ?>" class="btn-delete" onclick="return confirm('Delete this video?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
