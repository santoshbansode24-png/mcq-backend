<?php
/**
 * Check Content Version - Returns current content version for SmartCache
 */
header('Content-Type: application/json');
require_once '../config/db.php';

$board_type = $_GET['board_type'] ?? 'all';

try {
    // Return a version based on the latest created chapter or note
    $stmt = $pdo->query("
        SELECT MAX(last_update) as last_update FROM (
            SELECT MAX(created_at) as last_update FROM chapters
            UNION ALL
            SELECT MAX(created_at) as last_update FROM notes
        ) as t
    ");
    $row = $stmt->fetch();
    $version = $row && $row['last_update'] ? strtotime($row['last_update']) : 1;
    echo json_encode(['status' => 'success', 'version' => $version, 'board_type' => $board_type]);
} catch (Exception $e) {
    echo json_encode(['status' => 'success', 'version' => 1]);
}
?>
