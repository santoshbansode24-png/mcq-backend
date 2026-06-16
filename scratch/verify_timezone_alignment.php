<?php
require_once 'backend/config/db.php';

echo "PHP Current Timezone: " . date_default_timezone_get() . "\n";
echo "PHP Date: " . date('Y-m-d H:i:s') . "\n";

try {
    $stmt = $pdo->query("SELECT @@session.time_zone as tz, NOW() as now");
    $res = $stmt->fetch();
    echo "MySQL Connection Timezone: " . $res['tz'] . "\n";
    echo "MySQL NOW(): " . $res['now'] . "\n";
    
    if (abs(time() - strtotime($res['now'])) < 5) {
        echo "✅ SUCCESS: PHP and MySQL connection are perfectly aligned in timezone and time!\n";
    } else {
        echo "❌ FAILURE: Mismatch between PHP and MySQL connection time!\n";
    }
} catch (PDOException $e) {
    echo "DB Query failed: " . $e->getMessage() . "\n";
}
?>
