<?php
/**
 * Setup Reviewer Account Script
 * Automates the creation of the reviewer account in the production database.
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

echo "<h1>Reviewer Account Setup</h1>";

try {
    $email = 'reviewer@veeru.com';
    $name = 'Google Reviewer';
    $password = 'veeru123'; // Logic bypass ignores this, but we store it for reference
    $userType = 'student';
    $status = 'active';
    $classId = 3;

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        echo "<p style='color: blue;'>User already exists. Updating status to active...</p>";
        $stmt = $pdo->prepare("UPDATE users SET subscription_status = 'active', class_id = ? WHERE email = ?");
        $stmt->execute([$classId, $email]);
    } else {
        echo "<p>Creating new reviewer account...</p>";
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, user_type, subscription_status, class_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $userType, $status, $classId]);
    }

    echo "<h2 style='color: green;'>✅ SUCCESS!</h2>";
    echo "<p>Reviewer Account: <strong>$email</strong> is now LIVE and ACTIVE in your database.</p>";
    echo "<p>You can now use these credentials in the Play Store Review.</p>";
    echo "<hr>";
    echo "<p>Deleting this script is recommended after use for security.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ ERROR</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
