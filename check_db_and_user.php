<?php
require_once 'config/db.php';

// Check DB Connection
echo "Database Connection: OK\n\n";

// List Student Users
$stmt = $pdo->query("SELECT user_id, email, password, user_type, subscription_status FROM users WHERE user_type = 'student' LIMIT 5");
$users = $stmt->fetchAll();

echo "Student Users:\n";
foreach ($users as $user) {
    echo "ID: " . $user['user_id'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Pass Hash: " . substr($user['password'], 0, 10) . "...\n";
    echo "Status: " . $user['subscription_status'] . "\n";
    echo "-------------------\n";
}
?>
