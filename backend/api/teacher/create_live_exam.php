<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';
require_once '../../config/push_notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests allowed', null, 405);
}

// 1. Create table if not exists
$createTableQuery = "
CREATE TABLE IF NOT EXISTS live_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    chapter_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 15,
    status ENUM('active', 'completed') DEFAULT 'active',
    selected_mcq_ids TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (class_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($createTableQuery);
    
    // Ensure column exists for older tables
    try {
        $pdo->exec("ALTER TABLE live_exams ADD COLUMN selected_mcq_ids TEXT DEFAULT NULL");
    } catch (PDOException $alterEx) {
        // Ignore error if column already exists
    }
    try {
        $pdo->exec("ALTER TABLE live_exams ADD COLUMN selected_question_ids TEXT DEFAULT NULL");
    } catch (PDOException $alterEx) {
        // Ignore error if column already exists
    }
} catch (PDOException $e) {
    error_log("Error creating live_exams table: " . $e->getMessage());
    sendResponse('error', 'Database setup failed', null, 500);
}

// 2. Process Input
$input = getJsonInput();
$required = ['teacher_id', 'class_id', 'chapter_id', 'title', 'duration_minutes'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing fields: ' . implode(', ', $missing), null, 400);
}

// 3. Close any existing active exams for this class to prevent conflicts
try {
    $closeStmt = $pdo->prepare("UPDATE live_exams SET status = 'completed' WHERE class_id = ? AND status = 'active'");
    $closeStmt->execute([$input['class_id']]);
} catch (PDOException $e) {
    error_log("Error closing old exams: " . $e->getMessage());
}

// 4. Create new Live Exam
try {
    $stmt = $pdo->prepare("
        INSERT INTO live_exams (teacher_id, class_id, chapter_id, title, duration_minutes, status, selected_mcq_ids, selected_question_ids)
        VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
    ");
    
    $selected_ids = isset($input['selected_question_ids']) && is_array($input['selected_question_ids'])
        ? implode(',', array_map('intval', $input['selected_question_ids']))
        : null;
        
    $json_questions = isset($input['selected_question_ids']) && is_array($input['selected_question_ids'])
        ? json_encode(array_map('intval', $input['selected_question_ids']))
        : null;

    $stmt->execute([
        $input['teacher_id'],
        $input['class_id'],
        $input['chapter_id'],
        $input['title'],
        $input['duration_minutes'],
        $selected_ids,
        $json_questions
    ]);
    
    $examId = $pdo->lastInsertId();
    
    // 5. NOTIFICATION: Post to class_updates so students see it in their timeline
    try {
        // Fetch teacher name and school
        $tStmt = $pdo->prepare("SELECT name, school_name FROM users WHERE user_id = ?");
        $tStmt->execute([$input['teacher_id']]);
        $teacher = $tStmt->fetch(PDO::FETCH_ASSOC);
        $school_name = ($teacher && !empty($teacher['school_name'])) ? $teacher['school_name'] : 'School';

        $notifStmt = $pdo->prepare("
            INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload)
            VALUES (?, ?, ?, 'live_exam', ?, ?, ?)
        ");
        $notifStmt->execute([
            $input['teacher_id'],
            $school_name,
            $input['class_id'],
            "🔴 LIVE EXAM STARTED: " . $input['title'],
            "Your teacher has started a live exam. Click to join immediately! Duration: " . $input['duration_minutes'] . " mins.",
            json_encode(['exam_id' => $examId, 'duration' => $input['duration_minutes']])
        ]);

        // Trigger instant push notification to all students in the class
        sendClassPushNotifications(
            $pdo,
            $input['class_id'],
            "🔴 LIVE EXAM STARTED: " . $input['title'],
            "Your teacher has started a live exam. Click to join immediately! Duration: " . $input['duration_minutes'] . " mins.",
            [
                'type' => 'announcement',
                'screen' => 'ClassUpdates'
            ]
        );
    } catch (PDOException $notifEx) {
        error_log("Failed to post exam notification: " . $notifEx->getMessage());
    }

    sendResponse('success', 'Live Exam started successfully!', ['exam_id' => $examId], 200);
} catch (PDOException $e) {
    error_log("Error creating live exam: " . $e->getMessage());
    sendResponse('error', 'Failed to start live exam', null, 500);
}
?>
