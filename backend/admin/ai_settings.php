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
    
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_global_limit_daily', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$dailyLimit, $dailyLimit]);
        $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">✅ Settings updated successfully!</div>';
    } catch (Exception $e) {
        $message = '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">❌ Error: ' . $e->getMessage() . '</div>';
    }
}

// Get Current Settings
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_global_limit_daily'");
$currentLimit = $stmt->fetchColumn();
if (!$currentLimit) $currentLimit = 50000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Settings - Veeru Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        input[type="number"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; }
        button { background: #667eea; color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; width: 100%; transition: background 0.3s; }
        button:hover { background: #5a6fd1; }
        .back-link { display: block; margin-top: 20px; text-align: center; color: #666; text-decoration: none; }
        .info { font-size: 14px; color: #888; margin-top: 5px; }
    </style>
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
            
            <button type="submit">Update Settings</button>
        </form>
        
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>
