<?php
header('Content-Type: application/json');
require '../config/db.php';
require '../config/auth.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $teacher_id = $data['teacher_id'] ?? null;
    $class_id = $data['class_id'] ?? null;
    $chapter_id = $data['chapter_id'] ?? null;
    $title = $data['title'] ?? 'Live Exam';
    $duration_minutes = $data['duration_minutes'] ?? 15;
    $selected_question_ids = $data['selected_question_ids'] ?? [];

    if (!$teacher_id || !$class_id || !$chapter_id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    $json_questions = json_encode($selected_question_ids);

    $stmt = $pdo->prepare("INSERT INTO live_exams (teacher_id, class_id, chapter_id, title, duration_minutes, selected_question_ids, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$teacher_id, $class_id, $chapter_id, $title, $duration_minutes, $json_questions]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Live exam started successfully',
        'data' => [
            'live_exam_id' => $pdo->lastInsertId()
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
