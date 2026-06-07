<?php
/**
 * Test Push Notifications Web Endpoint
 * Hit: https://api.veeruapp.in/backend/api/test_push_web.php?class_id=11
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/push_notifications.php';
require_once __DIR__ . '/cors_middleware.php';

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 11;
$title = "Test Notification from Web Endpoint";
$message = "This is a real-time test push notification sent at " . date('Y-m-d H:i:s') . ". Tap to open Class Updates.";

echo "<h1>Veeru Push Notification Web Test</h1>";
echo "<p>Targeting class_id: $class_id</p>";

try {
    // Fetch student push tokens to display for debugging
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.push_token 
        FROM users u
        JOIN student_class_mapping scm ON u.user_id = scm.student_id
        WHERE scm.class_id = ?
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        echo "<p style='color:orange;'>No students mapped to class_id: $class_id.</p>";
    } else {
        echo "<p>Found " . count($students) . " student(s) in this class:</p><ul>";
        $hasTokens = false;
        foreach ($students as $student) {
            $tokenStr = $student['push_token'] ? htmlspecialchars($student['push_token']) : "No Push Token Registered";
            if ($student['push_token']) {
                $hasTokens = true;
            }
            echo "<li>Student ID: {$student['user_id']}, Name: " . htmlspecialchars($student['name']) . ", Push Token: <code>$tokenStr</code></li>";
        }
        echo "</ul>";
        
        if (!$hasTokens) {
            echo "<p style='color:red;'><strong>WARNING: None of the students in this class have push tokens registered.</strong><br/>Please log in to the student app on a physical device/dev-build first to register its push token.</p>";
        }
    }

    echo "<p>Dispatching push notifications...</p>";
    $sent = sendClassPushNotifications($pdo, $class_id, $title, $message, [
        'type' => 'announcement',
        'notification_id' => 99999,
        'screen' => 'ClassUpdates'
    ]);

    echo "<h2 style='color:green;'>RESULT: Successfully sent push notifications to $sent devices.</h2>";

} catch (Exception $e) {
    echo "<p style='color:red;'>ERROR: " . $e->getMessage() . "</p>";
}
?>
