<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM user_english_progress ORDER BY completed_at DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $rows], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
