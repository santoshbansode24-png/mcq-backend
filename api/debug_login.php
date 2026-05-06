<?php
/**
 * Debugging Teacher Login
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'cors_middleware.php';
require_once '../config/db.php';

$input = getJsonInput();
if (!$input) $input = $_POST;

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

echo "Debug Start<br>";
echo "Email: $email<br>";

try {
    echo "Querying users table...<br>";
    $stmt = $pdo->prepare("SELECT user_id, name, email, password, user_type, school_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        die("User not found for $email");
    }
    echo "User found: " . $user['name'] . " (Type: " . $user['user_type'] . ")<br>";

    if (!password_verify($password, $user['password'])) {
        die("Password mismatch");
    }
    echo "Password verified!<br>";

    echo "Checking stats...<br>";
    // Check if notifications table exists and has teacher_id
    try {
        $statsStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE teacher_id = ?");
        $statsStmt->execute([$user['user_id']]);
        echo "Stats checked successfully.<br>";
    } catch (Exception $e) {
        echo "Stats failed: " . $e->getMessage() . "<br>";
    }

    echo "Login logic complete. Final user data check:<br>";
    unset($user['password']);
    print_r($user);

} catch (Exception $e) {
    echo "CRASH: " . $e->getMessage();
}
?>
