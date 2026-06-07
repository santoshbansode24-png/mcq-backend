<?php
/**
 * Test Push Notifications Utility
 * Run via CLI: php api/test_push.php [class_id]
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/push_notifications.php';

$class_id = isset($argv[1]) ? intval($argv[1]) : 1;
$title = "Test Notification from Server";
$message = "This is a real-time test push notification sent at " . date('Y-m-d H:i:s') . ". Tap to open Class Updates.";

echo "=== Veeru Push Notification Test ===\n";
echo "Targeting class_id: $class_id\n\n";

try {
    // 1. Fetch student push tokens to display for debugging
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.push_token 
        FROM users u
        JOIN student_class_mapping scm ON u.user_id = scm.student_id
        WHERE scm.class_id = ?
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        echo "No students mapped to class_id: $class_id.\n";
        echo "To test, please ensure some students are assigned to this class in student_class_mapping table.\n";
    } else {
        echo "Found " . count($students) . " student(s) in this class:\n";
        $hasTokens = false;
        foreach ($students as $student) {
            $tokenStr = $student['push_token'] ? $student['push_token'] : "No Push Token Registered";
            if ($student['push_token']) {
                $hasTokens = true;
            }
            echo "- Student ID: {$student['user_id']}, Name: {$student['name']}, Push Token: $tokenStr\n";
        }
        
        if (!$hasTokens) {
            echo "\nWARNING: None of the students in this class have push tokens registered.\n";
            echo "Please log in to the student app on a physical device/dev-build first to register its push token.\n";
        }
    }

    echo "\nDispatching push notifications...\n";
    $sent = sendClassPushNotifications($pdo, $class_id, $title, $message, [
        'type' => 'announcement',
        'notification_id' => 99999,
        'screen' => 'ClassUpdates'
    ]);

    echo "RESULT: Successfully sent push notifications to $sent devices.\n";
    echo "====================================\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
