<?php
// Isolated Debug Login Script
// No external requires

$db_host = 'localhost';
$db_name = 'veeru_db';
$db_user = 'root';
$db_pass = '';
$db_port = '3306';

echo "<h1>Isolated Login Debug</h1>\n";

try {
    echo "Connecting to DB...\n";
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully.\n";

    $email = 'student@example.com'; 
    $password = 'student123';

    // 1. Check user
    echo "Checking user $email...\n";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "User NOT FOUND.\n";
        exit;
    }
    
    echo "User found: " . $user['name'] . "\n";
    echo "Sub Status: " . $user['subscription_status'] . "\n";

    // 2. Password
    if (password_verify($password, $user['password'])) {
        echo "Password: CORRECT\n";
    } else {
        echo "Password: INCORRECT\n";
    }

    // 3. Subscription
    if ($user['subscription_status'] !== 'active') {
        echo "Subscription check FAILED.\n";
    } else {
        echo "Subscription check PASSED.\n";
    }

    // 4. Update
    echo "Testing Streak Update...\n";
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET login_streak = login_streak + 1 WHERE user_id = ?");
        $updateStmt->execute([$user['user_id']]);
        echo "Streak update SUCCESS.\n";
    } catch (Exception $e) {
        echo "Streak update FAILED: " . $e->getMessage() . "\n";
    }

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
