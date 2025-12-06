<?php
/**
 * Users Management
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
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $type = $_POST['user_type'];
    $class_id = !empty($_POST['class_id']) ? $_POST['class_id'] : null;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, user_type, class_id, subscription_status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$name, $email, $password, $type, $class_id]);
        $message = "User added successfully!";
    } catch (PDOException $e) {
        $message = "Error: Email already exists";
    }
}

// Get Users
$users = $pdo->query("
    SELECT u.*, c.class_name 
    FROM users u 
    LEFT JOIN classes c ON u.class_id = c.class_id 
    WHERE user_type != 'admin' 
    ORDER BY u.created_at DESC
")->fetchAll();

// Get Classes for dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - MCQ Admin</title>
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
        
        /* Table Styles */
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { color: #666; font-weight: 600; background: #f9f9f9; }
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; }
        .badge-student { background: #e3f2fd; color: #2196f3; }
        .badge-teacher { background: #e8f5e9; color: #4caf50; }
        .btn-delete { color: #ff4444; text-decoration: none; font-weight: 500; }
        
        /* Form Styles */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .btn-add { background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        h2 { margin-bottom: 20px; color: #333; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎓 MCQ Admin Panel</h1>
        <a href="logout.php" style="color: white; text-decoration: none;">Logout</a>
    </div>
    
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="users.php" class="active">Users</a></li>
            <li><a href="classes.php">Classes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
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
            <h2>All Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Class</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
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
