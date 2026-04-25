<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

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

        $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">✅ Settings updated successfully!</div>';
    } catch (Exception $e) {
        $message = '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">❌ Error: ' . $e->getMessage() . '</div>';
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
    <link rel="stylesheet" href="admin_theme.css?v=1777115478">
</head>
<body>
    <div class="container">
        <h1>🤖 AI Token Limits</h1>
        <?php echo $message; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Daily Free Token Limit (Per Student)</label>
                <input type="number" name="daily_limit" value="<?php echo htmlspecialchars($currentLimit); ?>" required min="0">
                <div class="info">1,000 tokens ≈ 1-2 math problems. Default: 50,000.</div>
            </div>

            <div class="form-group">
                <label>Daily Request Count Limit (Per Student)</label>
                <input type="number" name="request_limit" value="<?php echo htmlspecialchars($currentRequestLimit); ?>" required min="0">
                <div class="info">How many times a student can use AI per day. Default: 5.</div>
            </div>
            
            <button type="submit">Update Settings</button>
        </form>
        
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>
