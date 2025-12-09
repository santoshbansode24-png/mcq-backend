<?php
// backend/reset_student.php
require_once 'config/db.php';

echo "<h1>Fixing Student Data & Subjects</h1>";

try {
    // 1. Ensure Class 10 exists
    $stmt = $pdo->prepare("INSERT IGNORE INTO classes (class_id, class_name) VALUES (10, 'Class 10')");
    $stmt->execute();
    echo "✅ Class 10 ensured.<br>";

    // 2. Ensure Subjects exist for Class 10
    $subjects = ['Mathematics', 'Science', 'English', 'Social Studies'];
    foreach ($subjects as $sub) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO subjects (class_id, subject_name, description) VALUES (10, ?, ?)");
        $stmt->execute([$sub, "$sub for Class 10"]);
    }
    echo "✅ Subjects for Class 10 ensured.<br>";

    // 3. Reset Student Password & Assign Class 10
    $email = 'student@example.com';
    $new_pass = 'student123';
    $hash = password_hash($new_pass, PASSWORD_DEFAULT);

    // Update Query
    $stmt = $pdo->prepare("UPDATE users SET password = ?, class_id = 10, subscription_status = 'active' WHERE email = ?");
    
    if ($stmt->execute([$hash, $email])) {
        echo "✅ User <strong>$email</strong> updated:<br>";
        echo "- Password: <strong>$new_pass</strong><br>";
        echo "- Class ID: <strong>10</strong><br>";
        echo "- Subscription: <strong>Active</strong><br>";
    } else {
        echo "❌ Failed to update user.<br>";
    }

    // 4. Check if user actually exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        // Create if missing
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, user_type, class_id, subscription_status) VALUES ('Student User', ?, ?, 'student', 10, 'active')");
        $stmt->execute([$email, $hash]);
        echo "✅ User was missing, so I created it fresh.<br>";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
