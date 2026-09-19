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

// Include Vendor Autoloader if available (for PDF parser & Word parser)
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

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

// Handle Add Chapter / Bulk Upload
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['form_action'] ?? 'add_single';

    if ($form_action === 'bulk_upload') {
        $subject_id = intval($_POST['bulk_subject_id'] ?? 0);
        
        if ($subject_id <= 0) {
            $message = "Error: Please select a valid class and subject.";
            $message_type = 'error';
        } elseif (!isset($_FILES['chapter_file']) || $_FILES['chapter_file']['error'] !== UPLOAD_ERR_OK) {
            $message = "Error: Please select a valid file to upload.";
            $message_type = 'error';
        } else {
            $file = $_FILES['chapter_file'];
            $filename = $file['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $tmp_path = $file['tmp_name'];
            
            $content = '';
            $chapter_names = [];
            
            if ($ext === 'pdf') {
                if (class_exists('\Smalot\PdfParser\Parser')) {
                    try {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($tmp_path);
                        $content = $pdf->getText();
                    } catch (\Exception $e) {
                        $message = "Error reading PDF: " . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = "Error: PDF parser library not found on server.";
                    $message_type = 'error';
                }
            } elseif ($ext === 'docx') {
                try {
                    $zip = new ZipArchive();
                    if ($zip->open($tmp_path) === true) {
                        if (($index = $zip->locateName('word/document.xml')) !== false) {
                            $xml = $zip->getFromIndex($index);
                            $content = strip_tags(str_replace(['</w:p>', '<w:br/>', '<w:tr/>'], "\n", $xml));
                        }
                        $zip->close();
                    }
                } catch (\Exception $e) {
                    $message = "Error reading DOCX: " . $e->getMessage();
                    $message_type = 'error';
                }
            } elseif ($ext === 'csv' || $ext === 'txt') {
                $content = file_get_contents($tmp_path);
                $content = convertUtf8($content);
            } elseif ($ext === 'json') {
                $content = file_get_contents($tmp_path);
                $content = convertUtf8($content);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    foreach ($data as $item) {
                        if (is_string($item) && !empty(trim($item))) {
                            $chapter_names[] = trim($item);
                        } elseif (is_array($item)) {
                            $cname = $item['chapter_name'] ?? $item['name'] ?? $item['title'] ?? '';
                            if (!empty(trim($cname))) {
                                $chapter_names[] = trim($cname);
                            }
                        }
                    }
                }
            } else {
                $message = "Error: Unsupported file format (.{$ext}). Please upload a .pdf, .docx, .csv, .txt, or .json file.";
                $message_type = 'error';
            }
            
            // Extract lines for text-based formats (PDF, DOCX, CSV, TXT)
            if (in_array($ext, ['pdf', 'docx', 'csv', 'txt']) && !empty($content)) {
                $lines = preg_split("/\r\n|\n|\r/", $content);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (empty($trimmed)) continue;
                    
                    // If CSV with commas, take the first non-empty column or whole line if no comma
                    if ($ext === 'csv' && strpos($trimmed, ',') !== false) {
                        $parts = str_getcsv($trimmed);
                        $col = trim($parts[0] ?? '');
                        if (!empty($col)) {
                            $trimmed = $col;
                        }
                    }
                    
                    // Ignore common header rows & junk lines
                    $lower = strtolower($trimmed);
                    if (in_array($lower, ['chapter_name', 'chapter name', 'chapters', 'title', 'subject', 'table of contents', 'contents'])) {
                        continue;
                    }
                    
                    // Skip lines that are just numbers or page numbers
                    if (is_numeric($trimmed) || strlen($trimmed) < 2) {
                        continue;
                    }
                    
                    $chapter_names[] = $trimmed;
                }
            }

            if (!empty($chapter_names)) {
                // Get highest current chapter_order for subject
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(chapter_order), 0) FROM chapters WHERE subject_id = ?");
                $stmt->execute([$subject_id]);
                $max_order = (int)$stmt->fetchColumn();

                // Fetch existing chapter names for duplicate check
                $stmt = $pdo->prepare("SELECT LOWER(chapter_name) FROM chapters WHERE subject_id = ?");
                $stmt->execute([$subject_id]);
                $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $existing_set = array_flip(array_map('strtolower', $existing));

                $inserted = 0;
                $skipped = 0;

                $insert_stmt = $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description, chapter_order) VALUES (?, ?, ?, ?)");
                
                foreach ($chapter_names as $cname) {
                    $sanitized_name = sanitizeInput($cname);
                    $lower_name = strtolower($sanitized_name);

                    if (empty($sanitized_name)) continue;

                    if (isset($existing_set[$lower_name])) {
                        $skipped++;
                        continue;
                    }

                    $max_order++;
                    $insert_stmt->execute([$subject_id, $sanitized_name, '', $max_order]);
                    $existing_set[$lower_name] = true;
                    $inserted++;
                }

                $message = "🎉 Bulk Upload Successful! Added {$inserted} new chapter(s).";
                if ($skipped > 0) {
                    $message .= " ({$skipped} duplicate chapter(s) skipped).";
                }
                $message_type = 'success';
            } elseif (empty($message)) {
                $message = "Error: No valid chapter names found in the uploaded file.";
                $message_type = 'error';
            }
        }
    } else {
        // Handle Single Add Chapter
        $subject_id = intval($_POST['subject_id']);
        $name = sanitizeInput($_POST['chapter_name']);
        $desc = sanitizeInput($_POST['description']);
        $order = intval($_POST['chapter_order']);
        
        if ($subject_id <= 0 || empty($name)) {
            $message = "Error: Please select a subject and enter a chapter name.";
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description, chapter_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$subject_id, $name, $desc, $order]);
                $message = "Chapter added successfully!";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "Error: Could not add chapter to database.";
                $message_type = 'error';
            }
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
    <style>
        /* Reusing Dashboard Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; color: #333; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; position: relative; }
        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav ul { list-style: none; display: flex; gap: 5px; }
        .nav li a { display: block; padding: 18px 25px; color: #666; text-decoration: none; font-weight: 500; border-bottom: 3px solid transparent; }
        .nav li a:hover, .nav li a.active { color: #667eea; border-bottom-color: #667eea; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 40px; }
        
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card h2 { font-size: 18px; margin-bottom: 15px; color: #444; display: flex; align-items: center; gap: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { color: #666; font-weight: 600; background: #f9f9f9; }
        .btn-logout { background: rgba(255,255,255,0.2); color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 13px; }
        
        .center-actions {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        .btn-switch-board {
            background: #ff9f43;
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
            background: #ffcd19;
            color: #333;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        select, input[type="text"], input[type="number"], input[type="file"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        select:focus, input:focus {
            border-color: #667eea;
        }
        .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-add:hover {
            opacity: 0.9;
        }
        .btn-bulk {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-bulk:hover {
            opacity: 0.9;
        }
        .btn-delete {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 4px;
            background: #fdf2f2;
        }
        .btn-delete:hover {
            background: #fde8e8;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #e8f8f5;
            color: #27ae60;
            border: 1px solid #a3e4d7;
        }
        .alert-error {
            background: #fdf2f2;
            color: #c0392b;
            border: 1px solid #f5b7b1;
        }
        .file-hint {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
            line-height: 1.5;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
    <script>
        const subjects = <?php echo json_encode($all_subjects); ?>;

        function filterSubjects(classSelectId, subjectSelectId) {
            const classId = document.getElementById(classSelectId).value;
            const subjectSelect = document.getElementById(subjectSelectId);
            
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            
            subjects.forEach(subject => {
                if (subject.class_id == classId) {
                    const option = document.createElement('option');
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
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php" class="active">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- 📁 Bulk Upload Chapters via File -->
            <div class="card">
                <h2>📁 Bulk Upload Chapters (File)</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="form_action" value="bulk_upload">
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                        <!-- Select Class -->
                        <select id="bulk_class_select" onchange="filterSubjects('bulk_class_select', 'bulk_subject_select')" required>
                            <option value="">Select Class</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Select Subject (Filtered) -->
                        <select name="bulk_subject_id" id="bulk_subject_select" required>
                            <option value="">Select Subject (Choose Class First)</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#555;">Upload File (.pdf, .docx, .csv, .txt, .json):</label>
                        <input type="file" name="chapter_file" accept=".pdf, .docx, .csv, .txt, .json" required>
                        <div class="file-hint">
                            📄 <strong>Supported Formats:</strong><br>
                            • <code>.pdf</code> or <code>.docx</code>: PDF or Word document text extraction.<br>
                            • <code>.txt</code> or <code>.csv</code>: Text/CSV chapter list (1 per line).<br>
                            • <code>.json</code>: JSON list of chapter names.
                        </div>
                    </div>
                    <button type="submit" class="btn-bulk">🚀 Upload & Extract Chapters</button>
                </form>
            </div>

            <!-- ➕ Add Single Chapter -->
            <div class="card">
                <h2>➕ Add Single Chapter</h2>
                <form method="POST">
                    <input type="hidden" name="form_action" value="add_single">
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                        <!-- Select Class -->
                        <select id="single_class_select" onchange="filterSubjects('single_class_select', 'single_subject_select')" required>
                            <option value="">Select Class</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Select Subject (Filtered) -->
                        <select name="subject_id" id="single_subject_select" required>
                            <option value="">Select Subject (Choose Class First)</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <input type="text" name="chapter_name" placeholder="Chapter Name" required style="margin-bottom:10px;">
                        <div style="display:flex; gap:10px;">
                            <input type="number" name="chapter_order" placeholder="Order (e.g. 1)" value="1" required style="width: 120px;">
                            <input type="text" name="description" placeholder="Description (Optional)" style="flex:1;">
                        </div>
                    </div>
                    <button type="submit" class="btn-add">Add Single Chapter</button>
                </form>
            </div>
        </div>

        <!-- 📚 All Chapters List -->
        <div class="card">
            <h2>📚 All Chapters (<?php echo htmlspecialchars($board_name); ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Class & Subject</th>
                        <th>Chapter Name</th>
                        <th>Order</th>
                        <th>Content</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($chapters)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:#888; padding:30px;">No chapters found for <?php echo htmlspecialchars($board_name); ?>. Select class & subject above to add chapters.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($chapters as $chapter): ?>
                    <tr>
                        <td>
                            <small style="color: #666;"><?php echo htmlspecialchars($chapter['class_name']); ?></small><br>
                            <strong><?php echo htmlspecialchars($chapter['subject_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($chapter['chapter_name']); ?></td>
                        <td><?php echo $chapter['chapter_order']; ?></td>
                        <td>
                            <?php echo $chapter['mcq_count']; ?> MCQs<br>
                            <?php echo $chapter['video_count']; ?> Videos
                        </td>
                        <td>
                            <a href="?delete=<?php echo $chapter['chapter_id']; ?>" class="btn-delete" onclick="return confirm('Delete this chapter?')">Delete</a>
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
