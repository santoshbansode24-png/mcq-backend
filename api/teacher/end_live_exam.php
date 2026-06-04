<?php
/**
 * End Live Exam API (Teacher) - Legacy Folder
 * Veeru
 * 
 * Endpoint: POST /api/teacher/end_live_exam.php
 */

require_once '../../config/db.php';
require_once '../cors_middleware.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['teacher_id']) || !isset($input['class_id']) || !isset($input['exam_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$teacher_id = intval($input['teacher_id']);
$class_id = intval($input['class_id']);
$exam_id = intval($input['exam_id']);

try {
    $stmt = $pdo->prepare("
        UPDATE live_exams 
        SET status = 'completed' 
        WHERE id = ? AND class_id = ? AND teacher_id = ?
    ");
    $stmt->execute([$exam_id, $class_id, $teacher_id]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Live Exam ended successfully.'
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
