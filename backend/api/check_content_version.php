<?php
/**
 * Check Content Version - Returns current content version for SmartCache
 */
header('Content-Type: application/json');
require_once '../config/db.php';

$board_type = $_GET['board_type'] ?? 'all';

try {
    // Return a simple version based on latest content update
    $stmt = $pdo->query("SELECT MAX(updated_at) as last_update FROM chapters LIMIT 1");
    $row = $stmt->fetch();
    $version = $row && $row['last_update'] ? strtotime($row['last_update']) : time();
    echo json_encode(['status' => 'success', 'version' => $version, 'board_type' => $board_type]);
} catch (Exception $e) {
    echo json_encode(['status' => 'success', 'version' => time()]);
}
?>
