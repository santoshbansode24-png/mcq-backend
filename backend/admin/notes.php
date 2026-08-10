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
    // Verify note belongs to current board
    $check = $pdo->prepare("
        SELECT n.note_id FROM notes n
        JOIN chapters ch ON n.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE n.note_id = ? AND c.board_type = ?
    ");
    $check->execute([$id, $selected_board]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM notes WHERE note_id = ?");
        $stmt->execute([$id]);
    }
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
                
                // Cloudflare R2 Upload Logic with Fallback
                require_once '../config/aws-config.php';
                
                $is_aws_configured = isR2Configured();
                $s3_url = false;

                if ($is_aws_configured) {
                    // Define S3 Key (Path in bucket)
                    $s3_key = "notes/" . $new_filename;
                    
                    // Upload directly from temp location to Cloudflare R2
                    $s3_url = uploadToS3($_FILES['pdf_file']['tmp_name'], $s3_key);
                }

                if ($s3_url) {
                    $file_path = $s3_url;
                } else {
                    // FALLBACK: Local Upload
                    if ($is_aws_configured) {
                         $message = "Warning: AWS Upload failed, falling back to local storage. Check logs.";
                    }
                    
                    $upload_dir = "../uploads/notes/";
                    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                    
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destination)) {
                        // Use full URL for consistency if needed, or relative path
                        // For now, keeping relative path for local files
                        $file_path = "uploads/notes/" . $new_filename;
                        
                        // If we wanted to alert the user about using local storage
                        // $message .= " Note uploaded locally.";
                    } else {
                        $message = "Error: Failed to upload file (Both AWS and Local failed).";
                    }
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
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();

$all_subjects_query = $pdo->prepare("
    SELECT s.* FROM subjects s 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE c.board_type = ? 
    ORDER BY s.subject_name
");
$all_subjects_query->execute([$selected_board]);
$all_subjects = $all_subjects_query->fetchAll();

$all_chapters_query = $pdo->prepare("
    SELECT ch.* FROM chapters ch 
    JOIN subjects s ON ch.subject_id = s.subject_id 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE c.board_type = ? 
    ORDER BY ch.chapter_order
");
$all_chapters_query->execute([$selected_board]);
$all_chapters = $all_chapters_query->fetchAll();

// Get Notes List
$notes_query = $pdo->prepare("
    SELECT n.*, ch.chapter_name, s.subject_name
    FROM notes n
    JOIN chapters ch ON n.chapter_id = ch.chapter_id
    JOIN subjects s ON ch.subject_id = s.subject_id
    JOIN classes c ON s.class_id = c.class_id
    WHERE c.board_type = ?
    ORDER BY n.created_at DESC LIMIT 200
");
$notes_query->execute([$selected_board]);
$notes = $notes_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notes - MCQ Admin</title>
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
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
        <h1>🎓 📓 Notes Management</h1>
        
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
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users</a></li>
            <li><a href="teachers.php"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a></li>
            <li><a href="classes.php"><i class="fa-solid fa-layer-group"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fa-solid fa-book"></i> Subjects</a></li>
            <li><a href="chapters.php"><i class="fa-solid fa-file-lines"></i> Chapters</a></li>
            <li><a href="mcqs.php"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php" class="active"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="audit_center.php"><i class="fa-solid fa-clipboard-check"></i> Audit Center</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
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
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
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
                        <option value="pdf_drive">PDF (Google Drive / Link)</option>
                        <option value="pdf_upload">PDF (Upload File)</option>
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
            <h2><i class="fa-solid fa-note-sticky"></i> All Notes (<?php echo $board_name; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-tag"></i> Title</th>
                        <th><i class="fa-solid fa-file-lines"></i> Chapter</th>
                        <th><i class="fa-solid fa-file-pdf"></i> Type</th>
                        <th><i class="fa-solid fa-bolt"></i> Action</th>
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
