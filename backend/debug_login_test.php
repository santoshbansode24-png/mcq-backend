<?php
// Debug Login Script
require_once 'config/db.php';

$email = 'student@example.com'; // Default email
$password = 'student123'; // Default password

echo "<h1>Login Debug</h1>";
echo "Testing login for: $email<br>";

try {
    // 1. Check user existence
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "User NOT FOUND.<br>";
        exit;
    }
    
    echo "User found: " . htmlspecialchars($user['name']) . "<br>";
    echo "User Type: " . htmlspecialchars($user['user_type']) . "<br>";
    echo "Subscription Status: " . htmlspecialchars($user['subscription_status']) . "<br>";

    // 2. Verify Password
    if (password_verify($password, $user['password'])) {
        echo "Password: CORRECT<br>";
    } else {
        echo "Password: INCORRECT<br>";
        // Hash for debugging
        echo " stored hash: " . $user['password'] . "<br>";
        echo " new hash of '$password': " . password_hash($password, PASSWORD_DEFAULT) . "<br>";
    }

    // 3. Check Subscription
    if ($user['subscription_status'] !== 'active') {
        echo "Subscription check FAILED. Expected 'active', got '" . $user['subscription_status'] . "'<br>";
    } else {
        echo "Subscription check PASSED.<br>";
    }

    // 4. Test Update (Streak)
    echo "Testing Streak Update...<br>";
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET login_streak = login_streak + 1 WHERE user_id = ?");
        $updateStmt->execute([$user['user_id']]);
        echo "Streak update SUCCESS.<br>";
    } catch (PDOException $e) {
        echo "Streak update FAILED: " . $e->getMessage() . "<br>";
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>
