<?php
/**
 * AI Settings - Admin Panel
 * Configure token and request limits
 */
session_start();
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

$message = '';

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dailyLimit = (int)$_POST['daily_limit'];
    $requestLimit = (int)$_POST['request_limit'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_global_limit_daily', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$dailyLimit, $dailyLimit]);

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_free_request_limit_daily', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$requestLimit, $requestLimit]);

        $message = '<div class="alert">✅ AI Settings updated successfully!</div>';
    } catch (Exception $e) {
        $message = '<div class="alert" style="background: #fee2e2; color: #991b1b;">❌ Error: ' . $e->getMessage() . '</div>';
    }
}

// Get Current Settings
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_global_limit_daily'");
$currentLimit = $stmt->fetchColumn();
if (!$currentLimit) $currentLimit = 50000;

$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_free_request_limit_daily'");
$currentRequestLimit = $stmt->fetchColumn();
if (!$currentRequestLimit) $currentRequestLimit = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Settings - Veeru Admin</title>
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css">
    <style>
        .ai-config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .ai-config-grid { grid-template-columns: 1fr; }
        }
        .info-box {
            background: rgba(255, 255, 255, 0.5);
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            border-left: 3px solid #667eea;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>🤖 AI Settings</h1>
        
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
            <li><a href="ai_settings.php" class="active"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <h2><i class="fa-solid fa-sliders"></i> Configure AI Token Limits</h2>
            <p style="color: #666; margin-bottom: 20px;">Control how much AI resources students can use daily. These settings apply globally across all boards.</p>
            
            <?php echo $message; ?>
            
            <form method="POST">
                <div class="ai-config-grid">
                    <div class="form-group">
                        <label><i class="fa-solid fa-coins"></i> Daily Token Limit (Per Student)</label>
                        <input type="number" name="daily_limit" value="<?php echo htmlspecialchars($currentLimit); ?>" required min="0" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <div class="info-box">
                            1,000 tokens ≈ 1-2 complex math problems. <br>
                            <strong>Recommended:</strong> 50,000 tokens.
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-paper-plane"></i> Daily Request Limit (Per Student)</label>
                        <input type="number" name="request_limit" value="<?php echo htmlspecialchars($currentRequestLimit); ?>" required min="0" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <div class="info-box">
                            Maximum number of AI interactions per day.<br>
                            <strong>Recommended:</strong> 5 to 10 requests.
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <button type="submit" class="btn-add" style="width: auto; padding: 12px 30px;"><i class="fa-solid fa-save"></i> Save Global AI Config</button>
                    <a href="dashboard.php" style="margin-left: 15px; color: #666; text-decoration: none;">Cancel</a>
                </div>
            </form>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="color: white;"><i class="fa-solid fa-circle-info"></i> Why Limit AI?</h3>
            <p style="margin-top: 10px; opacity: 0.9; line-height: 1.6;">
                Limiting AI usage helps prevent API abuse and keeps your operational costs predictable. 
                Students will see a "Limit Reached" message in the app once they hit either the Token or Request count limit.
            </p>
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 12px;">Google Gemini Pro</span>
                <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 12px;">Usage Audited</span>
            </div>
        </div>
    </div>
</body>
</html>
