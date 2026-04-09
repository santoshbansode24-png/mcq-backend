<?php
require_once 'cors_middleware.php';
header('Content-Type: application/json');
require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT target_date as exam_date FROM study_plans WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($plan) {
        echo json_encode([
            'status' => 'success',
            'is_configured' => true,
            'exam_date' => $plan['exam_date']
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'is_configured' => false
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
