<?php
/**
 * Notes Management
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
    $stmt = $pdo->prepare("DELETE FROM notes WHERE note_id = ?");
    $stmt->execute([$id]);
    header('Location: notes.php');
    exit();
}

// Handle Add Note
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $chapter_id = intval($_POST['chapter_id']);
    $title = sanitizeInput($_POST['title']);
    // Map User Selection to DB Type and Source
    $raw_type = $_POST['note_type'];
    if ($raw_type === 'pdf_upload') {
        $type = 'pdf';
        $source = 'upload';
    } elseif ($raw_type === 'pdf_drive') {
        $type = 'pdf';
        $source = 'url';
    } else {
        $type = $raw_type; // e.g. 'html'
        $source = '';
    }

    $content = '';
    $file_path = '';
    
    // Handle PDF Logic (Upload or URL)
    if ($type == 'pdf') {
        
        if ($source === 'url') {
            // Handle External URL
            $url = trim($_POST['pdf_url']);
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                $message = "Error: Please enter a valid URL.";
            } else {
                $file_path = $url;
            }
        } else {
            // Handle File Upload
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
                // ... (Existing upload logic remains same) ...
                $allowed = ['pdf' => 'application/pdf'];
                $filename = $_FILES['pdf_file']['name'];
                $filetype = $_FILES['pdf_file']['type'];
                $filesize = $_FILES['pdf_file']['size'];
        
                // Verify extension
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                if (!array_key_exists($ext, $allowed)) die("Error: Please select a valid file format.");
        
                // Verify size (50MB max)
                $maxsize = 50 * 1024 * 1024;
                if ($filesize > $maxsize) die("Error: File size is larger than the allowed limit (50MB).");
                
                // Check PHP system limit
                $php_limit = ini_get('upload_max_filesize');
                $php_limit_bytes = (int)$php_limit * 1024 * 1024; 
                if (stripos($php_limit, 'G') !== false) $php_limit_bytes *= 1024;
                if (stripos($php_limit, 'K') !== false) $php_limit_bytes /= 1024;
                
                if ($filesize > $php_limit_bytes) {
                     die("Error: File exceeds server setting ($php_limit).");
                }
    
                $new_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $filename);
                $upload_dir = "../uploads/notes/";
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destination)) {
                    $file_path = "uploads/notes/" . $new_filename;
                } else {
                    $message = "Error: Failed to move uploaded file.";
                }
            } else {
                $message = "Error: No file uploaded or upload error code: " . $_FILES['pdf_file']['error'];
            }
        }
    } else {
        $content = $_POST['content'];
    }
    
    if (empty($message)) { // Proceed only if no upload errors
        try {
            $stmt = $pdo->prepare("INSERT INTO notes (chapter_id, title, note_type, file_path, content) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$chapter_id, $title, $type, $file_path, $content]);
            $message = "Note added successfully!";
        } catch (PDOException $e) {
            $message = "Error: Database error - " . $e->getMessage();
        }
    }
}

// Get Classes for Initial Dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id")->fetchAll();

// Get All Subjects (for JS filtering)
$all_subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();

// Get All Chapters (for JS filtering)
$all_chapters = $pdo->query("SELECT * FROM chapters ORDER BY chapter_order")->fetchAll();

// Get Notes List
$notes = $pdo->query("
    SELECT n.*, ch.chapter_name, s.subject_name
    FROM notes n
    JOIN chapters ch ON n.chapter_id = ch.chapter_id
    JOIN subjects s ON ch.subject_id = s.subject_id
    ORDER BY n.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notes - MCQ Admin</title>
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
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php" class="active">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <h2>Add New Note</h2>
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
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

                    <input type="text" name="title" placeholder="Note Title" required>
                    <select name="note_type" id="note_type_select" onchange="toggleNoteInputs()">
                        <option value="pdf_upload">PDF (Upload File)</option>
                        <option value="pdf_drive">PDF (Google Drive / Link)</option>
                        <option value="html">HTML Content</option>
                    </select>
                    
                    <div id="source_upload" style="grid-column: span 2;">
                        <label style="display:block; margin-bottom:5px; font-weight:500;">Upload PDF:</label>
                        <input type="file" name="pdf_file" accept="application/pdf">
                        <small style="color:#666;">Max size: 50MB</small>
                    </div>

                    <div id="source_url" style="display:none; grid-column: span 2;">
                        <label style="display:block; margin-bottom:5px; font-weight:500;">PDF Link (Google Drive / Web URL):</label>
                        <input type="url" name="pdf_url" placeholder="https://drive.google.com/...">
                        <small style="color:#666;">Make sure the link is publicly accessible (Anyone with link).</small>
                    </div>

                    <textarea name="content" id="html_content" placeholder="Enter HTML content here..." style="display:none; grid-column: span 2; height: 150px;"></textarea>
                </div>
                <button type="submit" class="btn-add">Add Note</button>
            </form>
            
            <script>
                function toggleNoteInputs() {
                    const type = document.getElementById('note_type_select').value;
                    const uploadDiv = document.getElementById('source_upload');
                    const urlDiv = document.getElementById('source_url');
                    const htmlContent = document.getElementById('html_content');
                    
                    // Reset all
                    uploadDiv.style.display = 'none';
                    urlDiv.style.display = 'none';
                    htmlContent.style.display = 'none';
                    document.querySelector('input[name="pdf_file"]').required = false;
                    document.querySelector('input[name="pdf_url"]').required = false;
                    htmlContent.required = false;

                    if (type === 'pdf_upload') {
                        uploadDiv.style.display = 'block';
                        document.querySelector('input[name="pdf_file"]').required = true;
                    } 
                    else if (type === 'pdf_drive') {
                        urlDiv.style.display = 'block';
                        document.querySelector('input[name="pdf_url"]').required = true;
                    }
                    else if (type === 'html') {
                        htmlContent.style.display = 'block';
                        htmlContent.required = true;
                    }
                }
                // Initialize state
                toggleNoteInputs();
            </script>
        </div>
        </div>

        <div class="card">
            <h2>All Notes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Chapter</th>
                        <th>Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($notes as $note): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($note['title']); ?></td>
                        <td>
                            <small><?php echo htmlspecialchars($note['subject_name']); ?></small><br>
                            <?php echo htmlspecialchars($note['chapter_name']); ?>
                        </td>
                        <td><?php echo strtoupper($note['note_type']); ?></td>
                        <td>
                            <a href="?delete=<?php echo $note['note_id']; ?>" class="btn-delete" onclick="return confirm('Delete this note?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="text-align: center; margin-top: 30px; color: #888; font-size: 12px;">
            Veeru Admin v2.1 • Google Drive Support Added 🚀
        </div>
    </div>
</body>
</html>
