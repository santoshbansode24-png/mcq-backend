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
        
        $setupMessage = '<div class="alert">✅ Scholarship & Olympiad classes and subjects created successfully!</div>';
    } catch (Exception $e) {
        $setupMessage = '<div class="alert" style="background: #fee2e2; color: #991b1b; border-color: #fecaca;">❌ Error: ' . $e->getMessage() . '</div>';
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
    <!-- Premium Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header -->
    <div class="header glass">
        <h1>🎓 Veeru Admin</h1>
        
        <!-- Centered Switch Button -->
        <div class="center-actions">
            <a href="select_board.php" class="btn-switch-board">
                🔁 Switch Board
            </a>
            <span>Running: <?php echo htmlspecialchars($board_name); ?></span>
        </div>

        <div class="header-right">
            <div class="admin-info">
                <div class="name">
                    <span><?php echo htmlspecialchars($board_name); ?></span>
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
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users</a></li>
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
            <li><a href="../api/ai_billing_audit.php" target="_blank"><i class="fa-solid fa-receipt"></i> AI Billing</a></li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <?php echo $setupMessage; ?>
        
        <!-- Setup Scholarship Button (only show if Scholarship board is selected) -->
        <?php if ($selected_board === 'Scholarship'): ?>
        <div style="background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); padding: 30px; border-radius: 20px; margin-bottom: 40px; text-align: center; box-shadow: 0 10px 25px rgba(142, 45, 226, 0.3);">
            <h3 style="color: white; margin-bottom: 10px; font-weight: 800;">🏆 Scholarship & Olympiad Setup</h3>
            <p style="color: rgba(255,255,255,0.9); margin-bottom: 20px; font-size: 15px;">Automatically initialize level-wise subjects for the Scholarship board.</p>
            <a href="?setup_scholarship=1" onclick="return confirm('This will create Scholarship classes and subjects. Continue?')" style="background: white; color: #4A00E0; padding: 14px 35px; border-radius: 12px; text-decoration: none; font-weight: 800; display: inline-block;">
                ⚡ Initialize Scholarship Data
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-user-graduate"></i></div>
                <div class="label">Total Students</div>
                <div class="value"><?php echo $stats['student'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div class="label">Total Teachers</div>
                <div class="value"><?php echo $stats['teacher'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-book"></i></div>
                <div class="label">Total Subjects</div>
                <div class="value"><?php echo $stats['subjects'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-file-contract"></i></div>
                <div class="label">Total Chapters</div>
                <div class="value"><?php echo $stats['chapters'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-list-check"></i></div>
                <div class="label">Total MCQs</div>
                <div class="value"><?php echo $stats['mcqs'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-video"></i></div>
                <div class="label">Total Videos</div>
                <div class="value"><?php echo $stats['videos'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-note-sticky"></i></div>
                <div class="label">Total Notes</div>
                <div class="value"><?php echo $stats['notes'] ?? 0; ?></div>
            </div>

            <!-- New AI Settings Card -->
            <a href="ai_settings.php" class="stat-card ai-card" style="text-decoration: none;">
                <div class="icon"><i class="fa-solid fa-robot"></i></div>
                <div class="label">AI Settings</div>
                <div class="value" style="font-size: 20px;">Limits</div>
            </a>

            <a href="../api/ai_billing_audit.php" target="_blank" class="stat-card ai-card" style="text-decoration: none; border-color: #059669; background: #ecfdf5;">
                <div class="icon" style="background: #d1fae5; color: #059669;"><i class="fa-solid fa-receipt"></i></div>
                <div class="label" style="color: #059669;">AI Billing</div>
                <div class="value" style="font-size: 20px; color: #065f46;">View Audit</div>
            </a>
        </div>
        
        <!-- Recent Activity -->
        <div class="section">
            <h2><i class="fa-solid fa-chart-line"></i> Recent Quiz Attempts</h2>
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
                <div style="padding: 40px; text-align: center; color: var(--text-muted); font-weight: 600;">
                    No quiz attempts found for this board yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
