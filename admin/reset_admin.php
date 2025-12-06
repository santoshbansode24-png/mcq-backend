<?php
require_once '../config/db.php';

$email = 'admin@example.com';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing admin
        $update = $pdo->prepare("UPDATE users SET password = ?, user_type = 'admin', name = 'Admin User' WHERE email = ?");
        $update->execute([$hashed_password, $email]);
        echo "<h1>✅ Admin Password Reset Successfully!</h1>";
    } else {
        // Create new admin
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, user_type, subscription_status) VALUES (?, ?, ?, 'admin', 'active')");
        $insert->execute(['Admin User', $email, $hashed_password]);
        echo "<h1>✅ Admin Account Created Successfully!</h1>";
    }
    
    echo "<p>Email: <strong>$email</strong></p>";
    echo "<p>Password: <strong>$password</strong></p>";
    echo "<p><a href='index.php'>Go to Login Page</a></p>";

} catch (PDOException $e) {
    echo "<h1>❌ Error</h1>";
    echo $e->getMessage();
}
?>
