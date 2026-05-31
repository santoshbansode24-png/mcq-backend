<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../cors_middleware.php';

try {
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
    
    if ($class_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid class_id']);
        exit;
    }
    
    $query = "SELECT 
                le.id as live_exam_id, 
                le.title, 
                le.chapter_id,
                le.duration_minutes,
                le.status,
                le.created_at,
                c.chapter_name
              FROM live_exams le
              LEFT JOIN chapters c ON le.chapter_id = c.chapter_id
              WHERE le.class_id = ?
              ORDER BY le.created_at DESC
              LIMIT 20";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$class_id]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $exams]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Fatal Error: ' . $e->getMessage()
    ]);
}
?>
