<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

$class_code = isset($_GET['class_code']) ? $_GET['class_code'] : '';

if (empty($class_code)) {
    echo json_encode(['status' => 'error', 'message' => 'class_code required']);
    exit;
}

try {
    // Get the teacher mapped to this class_code
    $query = "
        SELECT tc.teacher_id, u.name as teacher_name
        FROM teacher_classes tc
        JOIN users u ON tc.teacher_id = u.user_id
        WHERE tc.class_code = ?
        LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$class_code]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacher) {
        echo json_encode(['status' => 'success', 'data' => $teacher]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Teacher not found for this class code']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error', 'details' => $e->getMessage()]);
}
?>
