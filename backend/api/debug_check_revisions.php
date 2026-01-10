<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require_once '../config/db.php';

try {
    $stmt = $pdo->query("
        SELECT qr.revision_id, qr.title, qr.chapter_id, c.chapter_name, s.subject_name, cl.class_name
        FROM quick_revision qr
        JOIN chapters c ON qr.chapter_id = c.chapter_id
        JOIN subjects s ON c.subject_id = s.subject_id
        JOIN classes cl ON s.class_id = cl.class_id
    ");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
