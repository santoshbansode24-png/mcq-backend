<?php
/**
 * Quick Revision Management
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
    // Verify revision belongs to current board
    $check = $pdo->prepare("
        SELECT qr.revision_id FROM quick_revision qr
        JOIN chapters ch ON qr.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE qr.revision_id = ? AND c.board_type = ?
    ");
    $check->execute([$id, $selected_board]);
    
    if ($check->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM quick_revision WHERE revision_id = ?");
        $stmt->execute([$id]);
    }
    header('Location: quick_revision.php');
    exit();
}

// Handle Add Revision
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $chapter_id = intval($_POST['chapter_id']);
    $title = sanitizeInput($_POST['title'] ?? '');
    if (empty($title)) {
        $stmtCh = $pdo->prepare("SELECT chapter_name FROM chapters WHERE chapter_id = ?");
        $stmtCh->execute([$chapter_id]);
        $ch_name = $stmtCh->fetchColumn() ?: 'Chapter';
        $title = $ch_name . " - Revision";
    }
    $summary = sanitizeInput($_POST['summary'] ?? '');
    if (empty($summary)) {
        $summary = "Quick revision notes for " . $title;
    }
    $key_points = [];

    // 1. Handle CSV Upload if present
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        
        // Read file content
        $content = file_get_contents($file);
        
        // Strip UTF-8 BOM if present (common when saving from Excel)
        $bom = pack('H*','EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Detect and Convert to UTF-8 (Vital for Marathi/Hindi text)
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }

        // Parse with memory stream for RFC 4180 CSV compliance (handles quotes, commas, multiline)
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rowNum = 0;
        while (($data = fgetcsv($stream)) !== false) {
            $rowNum++;
            if (empty($data) || count($data) < 2) continue;

            $q = trim($data[0] ?? '');
            $a = trim($data[1] ?? '');
            $e = trim($data[2] ?? '');

            // Skip header row
            if ($rowNum === 1 && (strtolower($q) === 'question' || strtolower($a) === 'answer')) {
                continue;
            }

            if (!empty($q) && !empty($a)) {
                $key_points[] = [
                    'q' => sanitizeInput($q), 
                    'a' => sanitizeInput($a),
                    'e' => sanitizeInput($e)
                ];
            }
        }
        fclose($stream);
    }

    // 2. Handle Manual Inputs
    $questions = $_POST['questions'] ?? [];
    $answers = $_POST['answers'] ?? [];
    $explanations = $_POST['explanations'] ?? [];
    
    for ($i = 0; $i < count($questions); $i++) {
        if (!empty(trim($questions[$i])) && !empty(trim($answers[$i]))) {
            $key_points[] = [
                'q' => sanitizeInput($questions[$i]),
                'a' => sanitizeInput($answers[$i]),
                'e' => isset($explanations[$i]) ? sanitizeInput($explanations[$i]) : ''
            ];
        }
    }
    
    if (empty($key_points)) {
        $message = "Error: Please add at least one Q&A pair via form or CSV.";
    } else {
        $json_points = json_encode($key_points, JSON_UNESCAPED_UNICODE);
        
        try {
            // Check if revision already exists for this chapter (Upsert)
            $checkExisting = $pdo->prepare("SELECT revision_id FROM quick_revision WHERE chapter_id = ?");
            $checkExisting->execute([$chapter_id]);
            $existingId = $checkExisting->fetchColumn();

            if ($existingId) {
                $stmt = $pdo->prepare("UPDATE quick_revision SET title = ?, summary = ?, key_points = ?, created_at = NOW() WHERE revision_id = ?");
                $stmt->execute([$title, $summary, $json_points, $existingId]);
                $message = "Quick Revision updated successfully! (" . count($key_points) . " points)";
            } else {
                $stmt = $pdo->prepare("INSERT INTO quick_revision (chapter_id, title, summary, key_points) VALUES (?, ?, ?, ?)");
                $stmt->execute([$chapter_id, $title, $summary, $json_points]);
                $message = "Quick Revision added successfully! (" . count($key_points) . " points)";
            }
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

// Get Revisions List
$revisions_query = $pdo->prepare("
    SELECT qr.*, ch.chapter_name, s.subject_name
    FROM quick_revision qr
    JOIN chapters ch ON qr.chapter_id = ch.chapter_id
    JOIN subjects s ON ch.subject_id = s.subject_id
    JOIN classes c ON s.class_id = c.class_id
    WHERE c.board_type = ?
    ORDER BY qr.created_at DESC
");
$revisions_query->execute([$selected_board]);
$revisions = $revisions_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quick Revision - MCQ Admin</title>
    <style>
        /* Reusing Dashboard Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav ul { list-style: none; display: flex; gap: 5px; flex-wrap: wrap; }
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

        /* Q&A Styles */
        .qa-container { margin-top: 15px; border: 1px solid #eee; padding: 15px; border-radius: 8px; }
        .qa-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: start; flex-wrap: wrap; }
        .qa-row input, .qa-row textarea { flex: 1; min-width: 200px; }
        .qa-row textarea { height: 38px; padding: 8px; resize: vertical; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; }
        .btn-small { padding: 5px 10px; font-size: 12px; border-radius: 4px; border: none; cursor: pointer; height: 38px; }
        .btn-remove { background: #ff4444; color: white; }
        .btn-plus { background: #28a745; color: white; margin-top: 10px; }
        
        .csv-section { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #ccc; }
        
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
    <script>
        // Pass PHP data to JS
        const subjects = <?php echo json_encode($all_subjects); ?>;
        const chapters = <?php echo json_encode($all_chapters); ?>;

        function filterSubjects() {
            const classId = document.getElementById('class_select').value;
            const subjectSelect = document.getElementById('subject_select');
            const chapterSelect = document.getElementById('chapter_select');
            
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            chapterSelect.innerHTML = '<option value="">Select Chapter (Choose Subject First)</option>';
            
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
            
            chapterSelect.innerHTML = '<option value="">Select Chapter</option>';
            
            chapters.forEach(chapter => {
                if (chapter.subject_id == subjectId) {
                    const option = document.createElement('option');
                    option.value = chapter.chapter_id;
                    option.textContent = chapter.chapter_name;
                    chapterSelect.appendChild(option);
                }
            });
        }

        function addQuaRow() {
            const container = document.getElementById('qa_list');
            const div = document.createElement('div');
            div.className = 'qa-row';
            div.innerHTML = `
                <input type="text" name="questions[]" placeholder="Question" required>
                <input type="text" name="answers[]" placeholder="Answer" required>
                <textarea name="explanations[]" placeholder="Explanation (Optional)"></textarea>
                <button type="button" class="btn-small btn-remove" onclick="this.parentElement.remove()">X</button>
            `;
            container.appendChild(div);
        }
    </script>
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
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php" class="active">Quick Revision</a></li>
            <li><a href="data_verification.php">Verify Data</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
            <li><a href="audit_center.php">Audit Center</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <h2>Add Quick Revision</h2>
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Dropdowns -->
                    <select id="class_select" onchange="filterSubjects()" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="subject_select" onchange="filterChapters()" required>
                        <option value="">Select Subject (Choose Class First)</option>
                    </select>

                    <select name="chapter_id" id="chapter_select" required>
                        <option value="">Select Chapter (Choose Subject First)</option>
                    </select>

                    <input type="text" name="title" placeholder="Revision Title (Optional - auto defaults to Chapter Name)" style="grid-column: span 3;">
                    
                    <textarea name="summary" placeholder="Chapter Summary (Optional)..." style="grid-column: span 3; height: 60px; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                </div>

                <div class="csv-section">
                    <h3>📂 Option 1: Upload CSV (Bulk Import)</h3>
                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">Format: <code>Question, Answer, Explanation</code> (3 Columns). First row header ignored.</p>
                    <input type="file" name="csv_file" accept=".csv" style="background: white;">
                    <br><br>
                    <a href="sample_quick_revision.csv" download style="font-size: 13px; color: #667eea; font-weight: 600;">⬇️ Download Sample CSV</a>
                </div>

                <div class="qa-container">
                    <h3>⚡ Option 2: Manual Key Points (Q&A)</h3>
                    <div id="qa_list">
                        <div class="qa-row">
                            <input type="text" name="questions[]" placeholder="Question">
                            <input type="text" name="answers[]" placeholder="Answer">
                            <textarea name="explanations[]" placeholder="Explanation (Optional)"></textarea>
                            <button type="button" class="btn-small btn-remove" onclick="this.parentElement.remove()">X</button>
                        </div>
                    </div>
                    <button type="button" class="btn-small btn-plus" onclick="addQuaRow()">+ Add Point</button>
                </div>

                <button type="submit" class="btn-add" style="margin-top: 20px; width: 100%;">Save Revision Content</button>
            </form>
        </div>

        <div class="card">
            <h2>Existing Revisions</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Chapter</th>
                        <th>Points</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($revisions as $rev): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($rev['title']); ?></strong></td>
                        <td>
                            <small style="color:#667eea;font-weight:600;"><?php echo htmlspecialchars($rev['subject_name']); ?></small><br>
                            <?php echo htmlspecialchars($rev['chapter_name']); ?>
                        </td>
                        <td>
                            <?php 
                                $points = json_decode($rev['key_points'], true);
                                echo is_array($points) ? count($points) : 0; 
                            ?> points
                        </td>
                        <td>
                            <button type="button" onclick='showPointsPreview(<?php echo htmlspecialchars(json_encode($points ?: []), ENT_QUOTES, "UTF-8"); ?>, <?php echo htmlspecialchars(json_encode($rev["title"]), ENT_QUOTES, "UTF-8"); ?>)' class="btn-small" style="background:#4f46e5;color:#fff;margin-right:8px;padding:6px 12px;border-radius:6px;cursor:pointer;">👁️ View</button>
                            <a href="?delete=<?php echo $rev['revision_id']; ?>" class="btn-delete" onclick="return confirm('Delete this revision?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;justify-content:center;align-items:center;">
        <div style="background:#fff;width:90%;max-width:700px;max-height:85vh;border-radius:16px;padding:25px;display:flex;flex-direction:column;box-shadow:0 10px 30px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:15px;">
                <h3 id="modalTitle" style="color:#1e293b;font-size:18px;">Revision Points</h3>
                <button type="button" onclick="closeModal()" style="background:#f1f5f9;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-weight:bold;font-size:16px;">✕</button>
            </div>
            <div id="modalBody" style="overflow-y:auto;flex:1;padding-right:10px;"></div>
        </div>
    </div>

    <script>
        function showPointsPreview(points, title) {
            document.getElementById('modalTitle').textContent = title || 'Revision Points';
            const body = document.getElementById('modalBody');
            body.innerHTML = '';
            if (!points || points.length === 0) {
                body.innerHTML = '<p style="color:#64748b;">No points found.</p>';
            } else {
                points.forEach((p, idx) => {
                    const q = p.q || p.Question || '';
                    const a = p.a || p.Answer || '';
                    const e = p.e || p.Explanation || '';
                    const itemDiv = document.createElement('div');
                    itemDiv.style.cssText = 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:12px;';
                    itemDiv.innerHTML = `
                        <div style="font-size:11px;font-weight:bold;color:#6366f1;margin-bottom:4px;">POINT ${idx + 1}</div>
                        <div style="font-weight:700;color:#0f172a;margin-bottom:6px;">❓ ${escapeHtml(q)}</div>
                        <div style="color:#16a34a;font-weight:600;margin-bottom:6px;">✅ ${escapeHtml(a)}</div>
                        ${e ? `<div style="font-size:13px;color:#475569;background:#eef2ff;padding:8px 12px;border-radius:8px;margin-top:6px;">💡 <em>${escapeHtml(e)}</em></div>` : ''}
                    `;
                    body.appendChild(itemDiv);
                });
            }
            document.getElementById('previewModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
