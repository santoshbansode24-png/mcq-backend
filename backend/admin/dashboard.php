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

// Handle Scholarship Setup
$setupMessage = '';
if (isset($_GET['setup_scholarship'])) {
    try {
        // Create 3 Scholarship Classes
        $pdo->exec("
            INSERT INTO classes (class_id, class_name, board_type) VALUES 
            (38, 'Scholarship - Primary Level (1-4)', 'Scholarship'),
            (39, 'Scholarship - Upper Primary Level (5-7)', 'Scholarship'),
            (40, 'Scholarship - Secondary Level (8-10)', 'Scholarship')
            ON DUPLICATE KEY UPDATE class_name = VALUES(class_name)
        ");
        
        // Subjects for Primary (38)
        $pdo->exec("
            INSERT INTO subjects (subject_name, class_id) VALUES 
            ('English', 38), ('Mathematics', 38), ('Mental Ability', 38), ('General Knowledge', 38), ('Mock Tests', 38)
            ON DUPLICATE KEY UPDATE subject_name = subject_name
        ");
        
        // Subjects for Upper Primary (39)
        $pdo->exec("
            INSERT INTO subjects (subject_name, class_id) VALUES 
            ('English', 39), ('Mathematics', 39), ('Science', 39), ('Mental Ability', 39), ('General Knowledge', 39), ('Mock Tests', 39)
            ON DUPLICATE KEY UPDATE subject_name = subject_name
        ");
        
        // Subjects for Secondary (40)
        $pdo->exec("
            INSERT INTO subjects (subject_name, class_id) VALUES 
            ('English', 40), ('Mathematics', 40), ('Science', 40), ('Mental Ability', 40), ('General Knowledge', 40), ('Social Science', 40), ('Mock Tests', 40)
            ON DUPLICATE KEY UPDATE subject_name = subject_name
        ");
        
        $setupMessage = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 40px; border: 1px solid #c3e6cb;">✅ Scholarship & Olympiad classes and subjects created successfully!</div>';
    } catch (Exception $e) {
        $setupMessage = '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px 40px; border: 1px solid #f5c6cb;">❌ Error: ' . $e->getMessage() . '</div>';
    }
}


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
        $stats['notes'] = $pdo->query("
            SELECT COUNT(*) FROM notes n 
            JOIN chapters ch ON n.chapter_id = ch.chapter_id 
            JOIN subjects s ON ch.subject_id = s.subject_id 
            WHERE s.class_id IN ($class_ids_str)
        ")->fetchColumn();

        // Count Students (Linked to this Board via Class OR directly via board_type)
        $stmtStudent = $pdo->prepare("
            SELECT COUNT(DISTINCT u.user_id) 
            FROM users u 
            LEFT JOIN classes c ON u.class_id = c.class_id 
            WHERE u.user_type = 'student' 
            AND (c.board_type = ? OR u.board_type = ? OR u.board = ?)
        ");
        $stmtStudent->execute([$selected_board, $selected_board, $selected_board]);
        $stats['student'] = $stmtStudent->fetchColumn();

        // Count Teachers (Global)
        $stats['teacher'] = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'teacher'")->fetchColumn();
    }
    
    // Fallback if no classes, still check for students registered to the board directly
    if (empty($valid_classes)) {
         $stmtStudent = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'student' AND (board_type = ? OR board = ?)");
         $stmtStudent->execute([$selected_board, $selected_board]);
         $stats['student'] = $stmtStudent->fetchColumn();
         
         $stats['teacher'] = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'teacher'")->fetchColumn();
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
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=1777115478">
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
            <div style="margin-top: 10px; font-weight: 800; color: #fff; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.3); white-space: nowrap;">
                Running: <span style="color: #667eea; background: #fff; padding: 3px 10px; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"><?php echo htmlspecialchars($board_name); ?></span>
            </div>
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
            <li><a href="ai_settings.php">🤖 AI Settings</a></li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <?php echo $setupMessage; ?>
        
        <!-- Setup Scholarship Button (only show if Scholarship board is selected) -->
        <?php if ($selected_board === 'Scholarship'): ?>
        <div style="background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); padding: 20px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
            <h3 style="color: white; margin-bottom: 10px;">🏆 Scholarship & Olympiad Setup</h3>
            <p style="color: rgba(255,255,255,0.9); margin-bottom: 15px; font-size: 14px;">Click below to create the 3 Scholarship levels and all subjects in the database</p>
            <a href="?setup_scholarship=1" onclick="return confirm('This will create Scholarship classes (Primary, Upper Primary, Secondary) and their subjects. Continue?')" style="background: white; color: #4A00E0; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 700; display: inline-block; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                ⚡ Setup Scholarship Data
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon" style="color: #4f46e5;"><i class="fa-solid fa-user-graduate"></i></div>
                <div class="label">Total Students</div>
                <div class="value"><?php echo $stats['student'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon" style="color: #10b981;"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div class="label">Total Teachers</div>
                <div class="value"><?php echo $stats['teacher'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon" style="color: #f59e0b;"><i class="fa-solid fa-book"></i></div>
                <div class="label">Total Subjects</div>
                <div class="value"><?php echo $stats['subjects'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon" style="color: #6366f1;"><i class="fa-solid fa-file-contract"></i></div>
                <div class="label">Total Chapters</div>
                <div class="value"><?php echo $stats['chapters'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon" style="color: #ec4899;"><i class="fa-solid fa-list-check"></i></div>
                <div class="label">Total MCQs</div>
                <div class="value"><?php echo $stats['mcqs'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon" style="color: #ef4444;"><i class="fa-solid fa-video"></i></div>
                <div class="label">Total Videos</div>
                <div class="value"><?php echo $stats['videos'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon" style="color: #8b5cf6;"><i class="fa-solid fa-note-sticky"></i></div>
                <div class="label">Total Notes</div>
                <div class="value"><?php echo $stats['notes'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon" style="color: #0ea5e9;"><i class="fa-solid fa-chart-line"></i></div>
                <div class="label">Quiz Attempts</div>
                <div class="value"><?php echo $stats['attempts'] ?? 0; ?></div>
            </div>

            <!-- New AI Settings Card -->
            <a href="ai_settings.php" class="stat-card" style="text-decoration: none; border: 2px solid #667eea; background: #eef2ff;">
                <div class="icon" style="color: #4338ca;"><i class="fa-solid fa-robot"></i></div>
                <div class="label" style="color: #4338ca; font-weight: bold;">AI Settings</div>
                <div class="value" style="font-size: 20px; color: #667eea;">Manage Limits</div>
            </a>
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
