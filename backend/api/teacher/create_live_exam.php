<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (class_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($createTableQuery);
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
        INSERT INTO live_exams (teacher_id, class_id, chapter_id, title, duration_minutes, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    
    $stmt->execute([
        $input['teacher_id'],
        $input['class_id'],
        $input['chapter_id'],
        $input['title'],
        $input['duration_minutes']
    ]);
    
    $examId = $pdo->lastInsertId();
    
    sendResponse('success', 'Live Exam started successfully!', ['exam_id' => $examId], 200);
} catch (PDOException $e) {
    error_log("Error creating live exam: " . $e->getMessage());
    sendResponse('error', 'Failed to start live exam', null, 500);
}
?>
