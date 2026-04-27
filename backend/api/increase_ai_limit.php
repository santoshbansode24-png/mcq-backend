<?php
require_once '../config/db.php';

header("Content-Type: text/html; charset=UTF-8");

echo "<h2>🚀 AI Limit Optimizer</h2>";

try {
    /** @var PDO $pdo */
    
    // 1. Increase daily free limit to 500
    $stmt1 = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_free_request_limit_daily', '500') ON DUPLICATE KEY UPDATE setting_value = '500'");
    $stmt1->execute();
    echo "<p style='color:green'>✅ Increased Daily Free Request Limit to 500.</p>";

    // 2. Increase global limit just in case
    $stmt2 = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_global_limit_daily', '1000000') ON DUPLICATE KEY UPDATE setting_value = '1000000'");
    $stmt2->execute();
    echo "<p style='color:green'>✅ Increased Global Daily Token Limit.</p>";

    echo "<p><strong>Your app's internal limits have been successfully removed! You can now test the AI feature freely.</strong></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
