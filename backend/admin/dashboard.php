<?php
/**
 * Admin Dashboard
 * Veeru
 */
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
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

// Get statistics filtered by board
try {
    // 1. Get Valid Class IDs for this board
    $stmtC = $pdo->prepare("SELECT class_id FROM classes WHERE board_type = ?");
    $stmtC->execute([$selected_board]);
    $valid_classes = $stmtC->fetchAll(PDO::FETCH_COLUMN);
    $class_ids_str = implode(',', $valid_classes);
    
    // If no classes exist for this board, stats are 0
    if (empty($valid_classes)) {
        $stats = [
            'classes' => 0, 'subjects' => 0, 'chapters' => 0, 
            'mcqs' => 0, 'videos' => 0, 'notes' => 0
        ];
    } else {
        // Statistics Queries (Filtered)
        $stats['classes'] = count($valid_classes);
        
        $stats['subjects'] = $pdo->query("SELECT COUNT(*) FROM subjects WHERE class_id IN ($class_ids_str)")->fetchColumn();
        
        // Use JOINs for deeper hierarchies
        $stats['chapters'] = $pdo->query("
            SELECT COUNT(*) FROM chapters ch 
            JOIN subjects s ON ch.subject_id = s.subject_id 
            WHERE s.class_id IN ($class_ids_str)
        ")->fetchColumn();
        
        $stats['mcqs'] = $pdo->query("
            SELECT COUNT(*) FROM mcqs m 
            JOIN chapters ch ON m.chapter_id = ch.chapter_id 
            JOIN subjects s ON ch.subject_id = s.subject_id 
            WHERE s.class_id IN ($class_ids_str)
        ")->fetchColumn();
        
        $stats['videos'] = $pdo->query("
            SELECT COUNT(*) FROM videos v 
            JOIN chapters ch ON v.chapter_id = ch.chapter_id 
            JOIN subjects s ON ch.subject_id = s.subject_id 
            WHERE s.class_id IN ($class_ids_str)
        ")->fetchColumn();
        
        $stats['notes'] = $pdo->query("
            SELECT COUNT(*) FROM notes n 
            JOIN chapters ch ON n.chapter_id = ch.chapter_id 
            JOIN subjects s ON ch.subject_id = s.subject_id 
            WHERE s.class_id IN ($class_ids_str)
        ")->fetchColumn();
    }
    
    // Recent activities (Global for now, or filter if we tracked student board)
    $recentStmt = $pdo->query("
        SELECT sp.*, u.name as student_name, ch.chapter_name, s.subject_name
        FROM student_progress sp
        JOIN users u ON sp.user_id = u.user_id
        JOIN chapters ch ON sp.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        ORDER BY sp.completed_at DESC
        LIMIT 10
    ");
    $recentActivities = $recentStmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Veeru</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        /* Header */
        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        /* Centered Switch Board Button */
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
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .admin-info {
            text-align: right;
        }
        
        .admin-info .name {
            font-weight: 600;
            font-size: 15px;
        }
        
        .admin-info .email {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Navigation */
        .nav {
            background: white;
            padding: 0 40px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .nav ul {
            list-style: none;
            display: flex;
            gap: 5px;
        }
        
        .nav li a {
            display: block;
            padding: 18px 25px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .nav li a:hover,
        .nav li a.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 40px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }
        
        /* Recent Activity */
        .section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .section h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-info .student {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .activity-info .details {
            font-size: 14px;
            color: #666;
        }
        
        .activity-score {
            text-align: right;
        }
        
        .activity-score .score {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        
        .activity-score .time {
            font-size: 12px;
            color: #999;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <!-- Header -->
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
    
    <!-- Navigation -->
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="classes.php">Classes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👨‍🎓</div>
                <div class="label">Total Students</div>
                <div class="value"><?php echo $stats['student'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon">👨‍🏫</div>
                <div class="label">Total Teachers</div>
                <div class="value"><?php echo $stats['teacher'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon">📚</div>
                <div class="label">Total Subjects</div>
                <div class="value"><?php echo $stats['subjects'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon">📖</div>
                <div class="label">Total Chapters</div>
                <div class="value"><?php echo $stats['chapters'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon">❓</div>
                <div class="label">Total MCQs</div>
                <div class="value"><?php echo $stats['mcqs'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon">🎥</div>
                <div class="label">Total Videos</div>
                <div class="value"><?php echo $stats['videos'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon">📝</div>
                <div class="label">Total Notes</div>
                <div class="value"><?php echo $stats['notes'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon">📊</div>
                <div class="label">Quiz Attempts</div>
                <div class="value"><?php echo $stats['attempts'] ?? 0; ?></div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="section">
            <h2>📈 Recent Quiz Attempts</h2>
            <?php if (!empty($recentActivities)): ?>
                <ul class="activity-list">
                    <?php foreach ($recentActivities as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-info">
                                <div class="student"><?php echo htmlspecialchars($activity['student_name']); ?></div>
                                <div class="details">
                                    <?php echo htmlspecialchars($activity['subject_name']); ?> - 
                                    <?php echo htmlspecialchars($activity['chapter_name']); ?>
                                </div>
                            </div>
                            <div class="activity-score">
                                <div class="score"><?php echo round($activity['percentage']); ?>%</div>
                                <div class="time"><?php echo date('M d, H:i', strtotime($activity['completed_at'])); ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="no-data">No quiz attempts yet</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
