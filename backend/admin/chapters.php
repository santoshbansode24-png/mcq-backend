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

// Handle Add Chapter
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = intval($_POST['subject_id']);
    $name = sanitizeInput($_POST['chapter_name']);
    $desc = sanitizeInput($_POST['description']);
    $order = intval($_POST['chapter_order']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description, chapter_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$subject_id, $name, $desc, $order]);
        $message = "Chapter added successfully!";
    } catch (PDOException $e) {
        $message = "Error: Database error";
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
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav ul { list-style: none; display: flex; gap: 5px; }
        .nav li a { display: block; padding: 18px 25px; color: #666; text-decoration: none; font-weight: 500; border-bottom: 3px solid transparent; }
        .nav li a:hover, .nav li a.active { color: #667eea; border-bottom-color: #667eea; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 40px; }
        
        /* Header Styles */
        .header { position: relative; }
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
        
        /* Card Enhancements */
        .card { 
            background: white; 
            border-radius: 20px; 
            padding: 0; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid #e8ecf1;
        }
        
        .add-chapter-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 35px;
            color: white;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .icon-wrapper {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: white;
        }
        
        .card-header h2::before {
            display: none;
        }
        
        .subtitle {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: rgba(255,255,255,0.9);
            font-weight: 400;
        }
        
        /* Modern Form Styling */
        .modern-form {
            padding: 35px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 2px;
        }
        
        .label-icon {
            font-size: 18px;
        }
        
        .label-text {
            flex: 1;
        }
        
        .required {
            color: #e53e3e;
            font-weight: 700;
            font-size: 16px;
        }
        
        .optional {
            color: #a0aec0;
            font-weight: 400;
            font-size: 13px;
            font-style: italic;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Segoe UI', sans-serif;
            transition: all 0.3s ease;
            background: white;
            color: #2d3748;
        }
        
        input:hover, select:hover, textarea:hover {
            border-color: #cbd5e0;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            transform: translateY(-1px);
        }
        
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 12 12'%3E%3Cpath fill='%23667eea' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            padding-right: 50px;
            font-weight: 500;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
            line-height: 1.6;
        }
        
        input::placeholder, select::placeholder, textarea::placeholder {
            color: #a0aec0;
        }
        
        /* Form Actions */
        .form-actions {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f0f4f8;
            display: flex;
            justify-content: flex-end;
        }
        
        .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-add:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }
        
        .btn-add:active {
            transform: translateY(-1px);
        }
        
        .btn-icon {
            font-size: 18px;
        }
        
        .alert {
            padding: 18px 25px;
            border-radius: 12px;
            margin: 25px 35px;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.2);
        }
        
        .alert::before {
            content: '✓';
            background: #28a745;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .btn-delete {
            color: #e53e3e;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-delete:hover {
            background: #fff5f5;
            transform: scale(1.05);
        }
        
        h2 {
            color: #2d3748;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        h2::before {
            content: '';
            width: 4px;
            height: 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        /* Table Styling */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        
        th, td { 
            padding: 18px; 
            text-align: left; 
            border-bottom: 1px solid #eee; 
        }
        
        th { 
            color: #4a5568; 
            font-weight: 600; 
            background: #f8f9fa;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tbody tr {
            transition: all 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9ff;
        }
    </style>
    <script>
        // Pass PHP data to JS
        const subjects = <?php echo json_encode($all_subjects); ?>;

        function filterSubjects() {
            const classId = document.getElementById('class_select').value;
            const subjectSelect = document.getElementById('subject_select');
            
            // Clear current options
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            
            // Filter and add new options
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
            <li><a href="chapters.php" class="active">Chapters</a></li>
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
        <!-- Add New Chapter Card -->
        <div class="card add-chapter-card">
            <div class="card-header">
                <div class="header-content">
                    <div class="icon-wrapper">📚</div>
                    <div>
                        <h2>Add New Chapter</h2>
                        <p class="subtitle">Create a new chapter for your selected board</p>
                    </div>
                </div>
            </div>
            
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            
            <form method="POST" class="modern-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <span class="label-icon">🎓</span>
                            <span class="label-text">Select Class</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <select id="class_select" onchange="filterSubjects()" required>
                                <option value="">Choose a class...</option>
                                <?php foreach($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="label-icon">📖</span>
                            <span class="label-text">Select Subject</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <select name="subject_id" id="subject_select" required>
                                <option value="">First select a class...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <span class="label-icon">📝</span>
                            <span class="label-text">Chapter Name</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <input type="text" name="chapter_name" placeholder="e.g., Introduction to Algebra" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="label-icon">🔢</span>
                            <span class="label-text">Chapter Order</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <input type="number" name="chapter_order" placeholder="1" value="1" min="1" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label>
                            <span class="label-icon">💬</span>
                            <span class="label-text">Description</span>
                            <span class="optional">(Optional)</span>
                        </label>
                        <div class="input-wrapper">
                            <textarea name="description" rows="3" placeholder="Brief description of the chapter..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-add">
                        <span class="btn-icon">✨</span>
                        <span>Add Chapter</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>All Chapters (<?php echo $board_name; ?>)</h2>
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
                    <?php foreach($chapters as $chapter): ?>
                    <tr>
                        <td>
                            <small style="color: #666;"><?php echo $chapter['class_name']; ?></small><br>
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
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
