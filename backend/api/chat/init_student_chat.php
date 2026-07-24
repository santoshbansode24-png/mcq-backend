<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

$input = getJsonInput();
$student_id = 0;

if (isset($input['student_id'])) {
    $student_id = (int)$input['student_id'];
} elseif (isset($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
} elseif (isset($_GET['user_id'])) {
    $student_id = (int)$_GET['user_id'];
}

if ($student_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'student_id required']);
    exit;
}

try {
    // Find the classroom the student belongs to (supporting both classroom ID and generic class level mappings)
    $query = "
        SELECT c.class_code, c.teacher_id, COALESCE(u.name, 'Teacher') as teacher_name 
        FROM student_class_mapping scm
        JOIN classrooms c ON (scm.class_id = c.class_id OR scm.class_id = c.class_level)
        LEFT JOIN users u ON c.teacher_id = u.user_id
        WHERE scm.student_id = ?
        ORDER BY scm.joined_at DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$student_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Student is not mapped to any specific teacher classroom']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error', 'details' => $e->getMessage()]);
}
?>
