<?php
/**
 * Teachers Management
 * Veeru
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}
require_once '../config/db.php';

// Check for Board Selection
if (!isset($_SESSION['admin_selected_board'])) {
    header('Location: select_board.php');
    exit();
}
$selected_board = $_SESSION['admin_selected_board'];
$board_name = $_SESSION['board_name'];

$message = '';

// Handle Delete Teacher
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Will also cascade delete from teacher_classes if foreign key exists,
    // otherwise manually delete mappings
    $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ?")->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $stmt->execute([$id]);
    header('Location: teachers.php');
    exit();
}

// Handle Add Teacher
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_teacher') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $mobile = sanitizeInput($_POST['mobile']); 
    $school_name = sanitizeInput($_POST['school_name']); 
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, phone, school_name, password, user_type, subscription_status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'teacher', 'active', NOW())");
        $stmt->execute([$name, $email, $mobile, $mobile, $school_name, $password]);
        $message = "<div class='alert'>Teacher added successfully!</div>";
    } catch (PDOException $e) {
        $message = "<div class='alert' style='background: #fee2e2; color: #991b1b;'>Error: " . $e->getMessage() . "</div>";
    }
}

// Handle Assign Class
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_class') {
    $teacher_id = intval($_POST['teacher_id']);
    $class_id = intval($_POST['class_id']);
    
    try {
        // Ensure column exists
        $pdo->exec("ALTER TABLE teacher_classes ADD COLUMN IF NOT EXISTS class_code VARCHAR(10) DEFAULT NULL");
        
        // Loop until a unique code is found
        $is_unique = false;
        $class_code = '';
        while (!$is_unique) {
            $class_code = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6));
            $check = $pdo->prepare("SELECT count(*) FROM teacher_classes WHERE class_code = ?");
            $check->execute([$class_code]);
            if ($check->fetchColumn() == 0) {
                $is_unique = true;
            }
        }
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO teacher_classes (teacher_id, class_id, class_code) VALUES (?, ?, ?)");
        $stmt->execute([$teacher_id, $class_id, $class_code]);
        $message = "<div class='alert'>✓ Class assigned successfully! Unique Code: <strong>" . $class_code . "</strong></div>";
    } catch (PDOException $e) {
        $message = "<div class='alert' style='background: #fee2e2; color: #991b1b;'>Error: " . $e->getMessage() . "</div>";
    }
}

// Handle Remove Assigned Class
if (isset($_GET['remove_class']) && isset($_GET['teacher_id'])) {
    $class_id = intval($_GET['remove_class']);
    $teacher_id = intval($_GET['teacher_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND class_id = ?");
        $stmt->execute([$teacher_id, $class_id]);
        header('Location: teachers.php');
        exit();
    } catch (PDOException $e) {
        $message = "<div class='alert' style='background: #fee2e2; color: #991b1b;'>Error removing class.</div>";
    }
}

// Get All Teachers
$teachers_stmt = $pdo->query("SELECT * FROM users WHERE user_type = 'teacher' ORDER BY created_at DESC");
$teachers = $teachers_stmt->fetchAll();

// Get Classes for this board (for assigning)
$classes_stmt = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes_stmt->execute([$selected_board]);
$board_classes = $classes_stmt->fetchAll();

// Fetch assigned classes for each teacher
$teacher_classes = [];
if (count($teachers) > 0) {
    $teacher_ids = implode(',', array_map(function($t) { return $t['user_id']; }, $teachers));
    $tc_stmt = $pdo->query("
        SELECT tc.teacher_id, tc.class_id, tc.class_code, c.class_name, c.board_type 
        FROM teacher_classes tc 
        JOIN classes c ON tc.class_id = c.class_id 
        WHERE tc.teacher_id IN ($teacher_ids)
    ");
    $assignments = $tc_stmt->fetchAll();
    foreach ($assignments as $a) {
        $teacher_classes[$a['teacher_id']][] = $a;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Veeru Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
    <style>
        .teacher-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
        .teacher-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .teacher-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        .teacher-name { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .teacher-school { font-size: 13px; color: #64748b; font-weight: 500; }
        .teacher-contact { font-size: 13px; color: #475569; margin-top: 8px; line-height: 1.6; }
        .assigned-classes { margin-top: 15px; }
        .assigned-classes h4 { font-size: 13px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 10px; letter-spacing: 0.5px; }
        .class-badges { display: flex; flex-wrap: wrap; gap: 8px; }
        .class-badge { display: inline-flex; align-items: center; background: #f1f5f9; color: #334155; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1; }
        .class-badge.current-board { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .class-badge .remove-class { margin-left: 6px; color: #ef4444; cursor: pointer; text-decoration: none; font-weight: bold; }
        .assign-form { margin-top: 15px; display: flex; gap: 10px; }
        .assign-form select { flex: 1; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; }
        .assign-form button { background: #3b82f6; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎓 Veeru Admin Panel</h1>
        <div class="center-actions">
            <a href="select_board.php" class="btn-switch-board">🔁 Switch Board</a>
        </div>
        <div class="header-right">
            <div class="admin-info">
                <div class="name">
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
            <li><a href="teachers.php" class="active"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a></li>
            <li><a href="classes.php"><i class="fa-solid fa-layer-group"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fa-solid fa-book"></i> Subjects</a></li>
            <li><a href="chapters.php"><i class="fa-solid fa-file-lines"></i> Chapters</a></li>
            <li><a href="mcqs.php"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <?php echo $message; ?>

        <!-- Add Teacher Form -->
        <div class="card">
            <h2><i class="fa-solid fa-user-plus"></i> Add New Teacher</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_teacher">
                <div class="form-grid">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="tel" name="mobile" placeholder="Mobile Number (Required)" required pattern="[0-9]{10}">
                    <input type="text" name="school_name" placeholder="School Name" required>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="btn-add" style="margin-top: 15px;">Add Teacher</button>
            </form>
        </div>

        <!-- Teachers List -->
        <h2 style="margin-top: 30px;"><i class="fa-solid fa-chalkboard-user"></i> Manage Teachers</h2>
        <div class="teacher-grid">
            <?php foreach($teachers as $teacher): ?>
            <div class="teacher-card">
                <div class="teacher-header">
                    <div>
                        <div class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                        <div class="teacher-school"><i class="fa-solid fa-school"></i> <?php echo htmlspecialchars($teacher['school_name'] ?? 'Not Assigned'); ?></div>
                        <div class="teacher-contact">
                            <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?><br>
                            <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($teacher['mobile']); ?>
                        </div>
                    </div>
                    <a href="?delete=<?php echo $teacher['user_id']; ?>" class="btn-delete" onclick="return confirm('Delete this teacher completely?')" style="padding: 6px 10px; font-size: 12px;"><i class="fa-solid fa-trash"></i></a>
                </div>
                
                <div class="assigned-classes">
                    <h4>Assigned Classes</h4>
                    <div class="class-badges">
                        <?php 
                        $t_id = $teacher['user_id'];
                        if (isset($teacher_classes[$t_id]) && count($teacher_classes[$t_id]) > 0) {
                            foreach ($teacher_classes[$t_id] as $tc) {
                                $isCurrentBoard = ($tc['board_type'] === $selected_board);
                                $badgeClass = $isCurrentBoard ? 'class-badge current-board' : 'class-badge';
                                $code = $tc['class_code'] ? " <span style='color:#6366f1;font-family:monospace;background:#e0e7ff;padding:2px 4px;border-radius:4px;margin-left:4px'>{$tc['class_code']}</span>" : "";
                                echo "<div class='{$badgeClass}'>Class {$tc['class_name']} {$code}
                                      <a href='?remove_class={$tc['class_id']}&teacher_id={$t_id}' class='remove-class' title='Remove' onclick='return confirm(\"Remove this class?\")'>×</a></div>";
                            }
                        } else {
                            echo "<span style='font-size: 12px; color: #94a3b8;'>No classes assigned yet.</span>";
                        }
                        ?>
                    </div>
                    
                    <form method="POST" class="assign-form">
                        <input type="hidden" name="action" value="assign_class">
                        <input type="hidden" name="teacher_id" value="<?php echo $t_id; ?>">
                        <select name="class_id" required>
                            <option value="">Assign Class...</option>
                            <?php foreach($board_classes as $bc): ?>
                                <option value="<?php echo $bc['class_id']; ?>">Class <?php echo $bc['class_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Assign</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
