<?php
require_once 'cors_middleware.php';
require_once '../config/db.php';

$email = isset($_GET['email']) ? $_GET['email'] : '';

if (empty($email)) {
    echo "<h1>Teacher Login Diagnostic Tool</h1>";
    echo "<p>Please provide an email to check: <code>?email=teacher@example.com</code></p>";
    exit();
}

try {
    echo "<h1>Diagnostic Report for: $email</h1>";
    
    // 1. Check in 'users' table
    $stmt = $pdo->prepare("SELECT user_id, name, email, user_type, password, school_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<h3 style='color: green;'>✅ Found in 'users' table</h3>";
        echo "<ul>";
        echo "<li>User ID: " . $user['user_id'] . "</li>";
        echo "<li>Name: " . $user['name'] . "</li>";
        echo "<li>User Type: <strong>" . $user['user_type'] . "</strong> " . ($user['user_type'] === 'teacher' ? '(Correct)' : '<span style="color:red;">(Incorrect - Must be teacher)</span>') . "</li>";
        echo "<li>School: " . $user['school_name'] . "</li>";
        echo "<li>Password Hash Length: " . strlen($user['password'] ?? '') . "</li>";
        echo "</ul>";
    } else {
        echo "<h3 style='color: red;'>❌ NOT found in 'users' table</h3>";
    }
    
    // 2. Check in 'teachers' table (old table)
    $stmt2 = $pdo->prepare("SELECT id, name, email FROM teachers WHERE email = ?");
    $stmt2->execute([$email]);
    $oldTeacher = $stmt2->fetch();
    
    if ($oldTeacher) {
        echo "<h3 style='color: orange;'>⚠️ Found in OLD 'teachers' table (Migration needed)</h3>";
        echo "<p>This user exists in the old system but hasn't been moved to the unified 'users' table yet.</p>";
    } else {
        echo "<h3>✅ Not in old 'teachers' table (No conflict)</h3>";
    }

    echo "<h3>System Recommendations:</h3>";
    if (!$user && $oldTeacher) {
        echo "<p><strong>Action:</strong> Run the migration tool to move this teacher to the 'users' table.</p>";
    } elseif ($user && $user['user_type'] !== 'teacher') {
        echo "<p><strong>Action:</strong> Update the user_type to 'teacher' in the database.</p>";
    } elseif (!$user && !$oldTeacher) {
        echo "<p><strong>Action:</strong> This email is not registered. Please register first.</p>";
    } else {
        echo "<p>Data looks correct. If login still fails, the issue is likely the password itself or the PWA's connection to this URL.</p>";
    }

} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ System Error: " . $e->getMessage() . "</h3>";
}
?>
