<?php
// backend/debug_login.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php'; // Use the standard config

echo "<h1>Login Debugger</h1>";

$email = 'student@example.com';
$password = 'student123';

echo "Checking user: <strong>$email</strong><br>";
echo "Testing password: <strong>$password</strong><br><br>";

// 1. Check connection
if (!isset($pdo)) {
    die("❌ Database connection variable \$pdo is not set. Check config/db.php");
}
echo "✅ Database connection active.<br>";

// 2. Fetch User
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("❌ User not found in database! The 'users' table might be empty or the email is wrong.");
}

echo "✅ User found: ID " . $user['user_id'] . " (" . $user['name'] . ")<br>";
echo "Stored Hash: " . $user['password'] . "<br><br>";

// 3. Verify Password
if (password_verify($password, $user['password'])) {
    echo "✅ <strong>SUCCESS:</strong> Password matches hash.<br>";
} else {
    echo "❌ <strong>FAILURE:</strong> Password does NOT match hash.<br>";
    echo "Generated Hash for 'student123': " . password_hash($password, PASSWORD_DEFAULT) . "<br>";
}
?>
