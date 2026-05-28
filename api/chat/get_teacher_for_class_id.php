<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($class_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'class_id required']);
    exit;
}

try {
    $query = "
        SELECT c.class_code, c.teacher_id, u.name as teacher_name 
        FROM classrooms c
        JOIN users u ON c.teacher_id = u.user_id
        WHERE c.class_id = ?
        LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$class_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Teacher not found for this class']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error', 'details' => $e->getMessage()]);
}
?>
