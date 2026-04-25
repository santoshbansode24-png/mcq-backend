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
require_once '../helpers/text_normalizer.php';

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
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = intval($_POST['subject_id']);
    $name = sanitizeInput($_POST['chapter_name']);
    $desc = sanitizeInput($_POST['description']);
    $order = intval($_POST['chapter_order']);
    
    // Normalize chapter name to UPPERCASE
    $normalized_name = normalizeChapterName($name);
    
    // Check for duplicates
    if (isDuplicateChapter($pdo, $normalized_name, $subject_id)) {
        $error = "⚠️ Duplicate Chapter: A chapter with this name already exists for the selected subject!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO chapters (subject_id, chapter_name, description, chapter_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$subject_id, $normalized_name, $desc, $order]);
            $message = "✓ Chapter added successfully! (Auto-capitalized to: $normalized_name)";
        } catch (PDOException $e) {
            $error = "❌ Error: Database error occurred";
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
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css">
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
            <?php if($error): ?><div class="alert" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); color: #721c24; border-color: #dc3545;"><span style="background: #dc3545;">✗</span><?php echo $error; ?></div><?php endif; ?>
            
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
