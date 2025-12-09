<?php
// backend/reset_student.php
require_once 'config/db.php';

echo "<h1>Resetting Student Password</h1>";

$email = 'student@example.com';
$new_pass = 'student123';
$hash = password_hash($new_pass, PASSWORD_DEFAULT);

// Update Query
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");

if ($stmt->execute([$hash, $email])) {
    echo "✅ Password for <strong>$email</strong> has been reset to: <strong>$new_pass</strong><br>";
    echo "Rows affected: " . $stmt->rowCount();
} else {
    echo "❌ Failed to update password.";
}
?>
