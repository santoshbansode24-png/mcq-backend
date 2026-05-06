<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests allowed', null, 405);
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($class_id <= 0) {
    sendResponse('error', 'Valid class_id required', null, 400);
}

try {
    // Look for an active exam for this class
    $stmt = $pdo->prepare("
        SELECT id as exam_id, title, chapter_id, duration_minutes, created_at 
        FROM live_exams 
        WHERE class_id = ? AND status = 'active'
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$class_id]);
    $activeExam = $stmt->fetch();

    if ($activeExam) {
        // Calculate if it has expired based on duration
        $createdAt = strtotime($activeExam['created_at']);
        $durationSeconds = $activeExam['duration_minutes'] * 60;
        $expiryTime = $createdAt + $durationSeconds;
        $currentTime = time();

        if ($currentTime > $expiryTime) {
            // Auto-close it
            $closeStmt = $pdo->prepare("UPDATE live_exams SET status = 'completed' WHERE id = ?");
            $closeStmt->execute([$activeExam['exam_id']]);
            sendResponse('success', 'No active exams', null, 200);
        } else {
            // Calculate remaining time
            $activeExam['remaining_seconds'] = $expiryTime - $currentTime;
            sendResponse('success', 'Active exam found', $activeExam, 200);
        }
    } else {
        sendResponse('success', 'No active exams', null, 200);
    }
} catch (PDOException $e) {
    // If table doesn't exist yet, just return no active exams
    if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        sendResponse('success', 'No active exams', null, 200);
    } else {
        error_log("Error checking live exam: " . $e->getMessage());
        sendResponse('error', 'Database error', null, 500);
    }
}
?>
