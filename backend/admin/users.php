<?php
/**
 * Users Management
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

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND user_type != 'admin'");
    $stmt->execute([$id]);
    header('Location: users.php');
    exit();
}

// Handle Add User
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $mobile = sanitizeInput($_POST['mobile']); // Get mobile number
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $type = $_POST['user_type'];
    $class_id = !empty($_POST['class_id']) ? $_POST['class_id'] : null;
    
    try {
        // Updated query to include mobile/phone columns
        $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, phone, password, user_type, class_id, subscription_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
        // Save mobile to both mobile and phone columns
        $stmt->execute([$name, $email, $mobile, $mobile, $password, $type, $class_id]);
        $message = "✓ User added successfully!";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "⚠️ Error: A user with this email address ($email) already exists.";
        } else {
            $message = "❌ Error: " . $e->getMessage();
        }
    }
}

// Get Filter Parameters
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_type = isset($_GET['f_type']) ? sanitizeInput($_GET['f_type']) : '';

// Build Query
$query_str = "
    SELECT u.*, c.class_name 
    FROM users u 
    LEFT JOIN classes c ON u.class_id = c.class_id 
    WHERE u.user_type != 'admin'
";

$params = [];

// Apply Board Filter (only for students, teachers are global usually but we check board context)
$query_str .= " AND (c.board_type = ? OR u.user_type = 'teacher')";
$params[] = $selected_board;

if ($search_query) {
    $query_str .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($filter_type) {
    $query_str .= " AND u.user_type = ?";
    $params[] = $filter_type;
}

$query_str .= " ORDER BY u.created_at DESC LIMIT 200";

$users_q = $pdo->prepare($query_str);
$users_q->execute($params);
$users = $users_q->fetchAll();

// Get Classes for dropdown
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - MCQ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
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
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="users.php" class="active"><i class="fa-solid fa-users"></i> Users</a></li>
            <li><a href="teachers.php"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a></li>
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
        <!-- Add User Form -->
        <div class="card">
            <h2>Add New User</h2>
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-grid">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="tel" name="mobile" placeholder="Mobile Number (Required)" required pattern="[0-9]{10}" title="Ten digit mobile number">
                    <input type="password" name="password" placeholder="Password" required>
                    <select name="user_type" required onchange="this.value=='student'?document.getElementById('class_select').style.display='block':document.getElementById('class_select').style.display='none'">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                    <select name="class_id" id="class_select">
                        <option value="">Select Class (Students only)</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo $class['class_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-add">Add User</button>
            </form>
        </div>

        <!-- Users List -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>All Users</h2>
                <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="search" placeholder="Name, Email or Phone..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 250px; margin-bottom: 0;">
                    <select name="f_type" style="margin-bottom: 0; width: 130px;">
                        <option value="">All Types</option>
                        <option value="student" <?php echo $filter_type == 'student' ? 'selected' : ''; ?>>Students</option>
                        <option value="teacher" <?php echo $filter_type == 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                    </select>
                    <button type="submit" class="btn-add" style="margin: 0; padding: 10px 15px;"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if($search_query || $filter_type): ?>
                        <a href="users.php" class="btn-delete" style="padding: 10px; text-decoration: none;"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><i class="fa-solid fa-user"></i> Name</th>
                        <th><i class="fa-solid fa-school"></i> School</th>
                        <th><i class="fa-solid fa-envelope"></i> Email</th>
                        <th><i class="fa-solid fa-phone"></i> Phone</th>
                        <th><i class="fa-solid fa-id-card"></i> Type</th>
                        <th><i class="fa-solid fa-graduation-cap"></i> Class</th>
                        <th><i class="fa-solid fa-calendar-day"></i> Joined</th>
                        <th><i class="fa-solid fa-bolt"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach($users as $user): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td>
                            <?php echo htmlspecialchars($user['name']); ?>
                            <?php if(!empty($user['board'])): ?>
                                <br><small style="color:#888"><?php echo htmlspecialchars($user['board']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['school_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars(!empty($user['mobile']) ? $user['mobile'] : ($user['phone'] ?? '-')); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $user['user_type']; ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                        </td>
                        <td><?php echo $user['class_name'] ?? '-'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <a href="?delete=<?php echo $user['user_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
