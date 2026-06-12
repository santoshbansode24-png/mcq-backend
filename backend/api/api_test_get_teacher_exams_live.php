<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'cors_middleware.php';
require_once '../config/db.php';

try {
    $stmt = $pdo->prepare("
        SELECT cu.id as update_id, cu.class_id, cu.title, cu.message, cu.created_at, c.class_name
        FROM class_updates cu
        LEFT JOIN classes c ON cu.class_id = c.class_id
        WHERE cu.teacher_id = ? AND cu.update_type IN ('exam', 'live_exam')
        ORDER BY cu.created_at DESC
    ");
    $stmt->execute([1]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'file_version' => '1.0.1_debug',
        'exams' => $exams
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
