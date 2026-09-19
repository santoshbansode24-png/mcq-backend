<?php
/**
 * Chapters Management with Bulk Upload
 * Veeru
 */
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
if (file_exists('../helpers/text_normalizer.php')) {
    require_once '../helpers/text_normalizer.php';
}
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
} elseif (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
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
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['form_action'] ?? 'add_single';

    if ($form_action === 'bulk_upload') {
        $subject_id = intval($_POST['bulk_subject_id'] ?? 0);
        
        if ($subject_id <= 0) {
            $error = "Please select a valid class and subject.";
        } elseif (!isset($_FILES['chapter_file']) || $_FILES['chapter_file']['error'] !== UPLOAD_ERR_OK) {
            $error = "Please select a valid file to upload.";
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
                        $error = "Error reading PDF: " . $e->getMessage();
                    }
                } else {
                    $error = "PDF parser library not installed on server.";
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
                    $error = "Error reading DOCX: " . $e->getMessage();
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
                $error = "Unsupported file format (.{$ext}). Please upload .pdf, .docx, .csv, .txt, or .json file.";
            }
            
            // Extract lines for text-based formats
            if (in_array($ext, ['pdf', 'docx', 'csv', 'txt']) && !empty($content)) {
                $lines = preg_split("/\r\n|\n|\r/", $content);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (empty($trimmed)) continue;
                    
                    if ($ext === 'csv' && strpos($trimmed, ',') !== false) {
                        $parts = str_getcsv($trimmed);
                        $col = trim($parts[0] ?? '');
                        if (!empty($col)) {
                            $trimmed = $col;
                        }
                    }
                    
                    $lower = strtolower($trimmed);
                    if (in_array($lower, ['chapter_name', 'chapter name', 'chapters', 'title', 'subject', 'table of contents', 'contents'])) {
                        continue;
                    }
                    
                    if (is_numeric($trimmed) || strlen($trimmed) < 2) {
                        continue;
                    }
                    
                    $chapter_names[] = $trimmed;
                }
            }

            if (!empty($chapter_names)) {
                // Get highest current order
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(chapter_order), 0) FROM chapters WHERE subject_id = ?");
                $stmt->execute([$subject_id]);
                $max_order = (int)$stmt->fetchColumn();

                // Get existing for duplicate check
                $stmt = $pdo->prepare("SELECT LOWER(chapter_name) FROM chapters WHERE subject_id = ?");
                $stmt->execute([$subject_id]);
                $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $existing_set = array_flip(array_map('strtolower', $existing));

                $inserted = 0;
                $skipped = 0;

                $insert_stmt = $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description, chapter_order) VALUES (?, ?, ?, ?)");
                
                foreach ($chapter_names as $cname) {
                    $sanitized_name = sanitizeInput($cname);
                    if (function_exists('normalizeChapterName')) {
                        $sanitized_name = normalizeChapterName($sanitized_name);
                    }
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

                $message = "🎉 Bulk Upload Complete! Added {$inserted} new chapter(s).";
                if ($skipped > 0) {
                    $message .= " ({$skipped} duplicates skipped).";
                }
            } elseif (empty($error)) {
                $error = "No valid chapter names found in the uploaded file.";
            }
        }
    } else {
        // Handle Single Add Chapter
        $subject_id = intval($_POST['subject_id'] ?? 0);
        $name = sanitizeInput($_POST['chapter_name'] ?? '');
        $desc = sanitizeInput($_POST['description'] ?? '');
        $order = intval($_POST['chapter_order'] ?? 1);
        
        if ($subject_id <= 0 || empty($name)) {
            $error = "Please select a subject and enter a chapter name.";
        } else {
            if (function_exists('normalizeChapterName')) {
                $name = normalizeChapterName($name);
            }
            try {
                $stmt = $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description, chapter_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$subject_id, $name, $desc, $order]);
                $message = "✓ Chapter added successfully!";
            } catch (PDOException $e) {
                $error = "Database error: Could not add chapter.";
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
    <!-- Modern Admin CSS Theme -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
    <style>
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        .file-hint-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 12px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 13px;
            color: #475569;
            line-height: 1.5;
        }
        .btn-bulk-upload {
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-bulk-upload:hover {
            background: #059669;
        }
    </style>
    <script>
        const subjects = <?php echo json_encode($all_subjects); ?>;

        function filterSubjects(classSelectId, subjectSelectId) {
            const classId = document.getElementById(classSelectId).value;
            const subjectSelect = document.getElementById(subjectSelectId);
            
            subjectSelect.innerHTML = '<option value="">Select Subject (Choose Class First)</option>';
            
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
        
        <div class="header-right">
            <a href="select_board.php" class="btn-switch-board">
                <i class="fa-solid fa-rotate"></i> Switch Board
            </a>
            <div class="admin-info">
                <div class="name">
                    <span style="background: rgba(37, 99, 235, 0.1); color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                        <?php echo htmlspecialchars($board_name); ?>
                    </span>
                    &nbsp; <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                </div>
                <div class="email"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></div>
            </div>
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
    
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users</a></li>
            <li><a href="teachers.php"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a></li>
            <li><a href="classes.php"><i class="fa-solid fa-layer-group"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fa-solid fa-book"></i> Subjects</a></li>
            <li><a href="chapters.php" class="active"><i class="fa-solid fa-file-lines"></i> Chapters</a></li>
            <li><a href="mcqs.php"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="audit_center.php"><i class="fa-solid fa-clipboard-check"></i> Audit Center</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <?php if($message): ?><div class="alert success"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert" style="background: #f8d7da; color: #721c24; border-color: #dc3545;"><?php echo $error; ?></div><?php endif; ?>

        <div class="grid-2">
            <!-- 📁 Bulk Upload Chapters via File -->
            <div class="card">
                <h2><i class="fa-solid fa-cloud-arrow-up" style="color: #10b981;"></i> Bulk Upload Chapters (File)</h2>
                <p style="margin-bottom: 15px; color: #666; font-size: 14px;">Adding to: <strong><?php echo htmlspecialchars($board_name); ?></strong></p>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="form_action" value="bulk_upload">
                    
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">1. Select Class:</label>
                        <select id="bulk_class_select" onchange="filterSubjects('bulk_class_select', 'bulk_subject_select')" required>
                            <option value="">Select Class</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">2. Select Subject:</label>
                        <select name="bulk_subject_id" id="bulk_subject_select" required>
                            <option value="">Select Subject (Choose Class First)</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 13px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">3. Upload File (.pdf, .docx, .csv, .txt, .json):</label>
                        <input type="file" name="chapter_file" accept=".pdf, .docx, .csv, .txt, .json" required>
                        
                        <div class="file-hint-box">
                            <strong>📄 Supported Formats:</strong><br>
                            • <code>.csv</code> or <code>.txt</code>: Chapter names list (1 per line).<br>
                            • <code>.pdf</code> or <code>.docx</code>: Extracts chapter list automatically.<br>
                            • <code>.json</code>: Array of chapter names.
                        </div>
                    </div>

                    <button type="submit" class="btn-bulk-upload"><i class="fa-solid fa-file-export"></i> Upload & Extract Chapters</button>
                </form>
            </div>

            <!-- ➕ Add Single Chapter -->
            <div class="card">
                <h2><i class="fa-solid fa-plus-circle" style="color: #2563eb;"></i> Add New Chapter</h2>
                <p style="margin-bottom: 15px; color: #666; font-size: 14px;">Adding to: <strong><?php echo htmlspecialchars($board_name); ?></strong></p>
                
                <form method="POST">
                    <input type="hidden" name="form_action" value="add_single">
                    
                    <div style="margin-bottom: 12px;">
                        <select id="single_class_select" onchange="filterSubjects('single_class_select', 'single_subject_select')" required>
                            <option value="">Select Class</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <select name="subject_id" id="single_subject_select" required>
                            <option value="">Select Subject (Choose Class First)</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <input type="text" name="chapter_name" placeholder="Chapter Name" required>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <input type="text" name="description" placeholder="Description (Optional)">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <input type="number" name="chapter_order" placeholder="Order (e.g. 1)" value="1" required style="width: 120px;">
                    </div>

                    <button type="submit" class="btn-add"><i class="fa-solid fa-plus"></i> Add Chapter</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-file-lines"></i> All Chapters (<?php echo htmlspecialchars($board_name); ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-book"></i> Class & Subject</th>
                        <th><i class="fa-solid fa-heading"></i> Chapter Name</th>
                        <th><i class="fa-solid fa-sort"></i> Order</th>
                        <th><i class="fa-solid fa-layer-group"></i> Content</th>
                        <th><i class="fa-solid fa-bolt"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($chapters)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #666; padding: 20px;">No chapters found for this board.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($chapters as $chapter): ?>
                    <tr>
                        <td>
                            <small style="color: #666;"><?php echo htmlspecialchars($chapter['class_name']); ?></small><br>
                            <strong><?php echo htmlspecialchars($chapter['subject_name']); ?></strong>
                        </td>
                        <td><strong><?php echo htmlspecialchars($chapter['chapter_name']); ?></strong></td>
                        <td><span style="background: #f1f5f9; padding: 4px 10px; border-radius: 4px; font-weight: 600;"><?php echo $chapter['chapter_order']; ?></span></td>
                        <td>
                            <span style="background: #e0e7ff; color: #4338ca; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo $chapter['mcq_count']; ?> MCQs</span>
                            <span style="background: #fef3c7; color: #b45309; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo $chapter['video_count']; ?> Videos</span>
                        </td>
                        <td>
                            <a href="?delete=<?php echo $chapter['chapter_id']; ?>" class="btn-delete" onclick="return confirm('Delete this chapter?')"><i class="fa-solid fa-trash"></i> Delete</a>
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
