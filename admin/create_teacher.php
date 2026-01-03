<?php
// Fix path
$dbPath = dirname(__DIR__) . '/config/db.php';
if (!file_exists($dbPath)) {
    die("DB Config not found at: $dbPath");
}
require_once $dbPath;

try {
    $email = 'teacher@example.com';
    $password = 'teacher123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "Teacher already exists.\n";
        // Update password just in case
        $update = $pdo->prepare("UPDATE users SET password = ?, user_type = 'teacher' WHERE email = ?");
        $update->execute([$hash, $email]);
        echo "Password updated to 'teacher123'.\n";
    } else {
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, phone, user_type) VALUES ('Demo Teacher', ?, ?, '1234567890', 'teacher')");
        $insert->execute([$email, $hash]);
        echo "Created teacher: $email / $password\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
