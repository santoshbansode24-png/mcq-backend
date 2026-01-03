<?php
require_once 'config/db.php';
try {
    $stmt = $pdo->query("DESCRIBE user_vocab_stats");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(["status" => "success", "columns" => $columns]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
