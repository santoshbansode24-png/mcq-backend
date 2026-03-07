<?php
/**
 * Check Board-Specific Content Version
 * Returns the timestamp of the last update to trigger a Smart Sync on the app.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../config/db.php';

try {
    $boardType = isset($_GET['board_type']) ? $_GET['board_type'] : 'CBSE';
    $userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;

    // 1. Try to get board-specific version from the tracking table
    $stmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(last_update) as version FROM app_content_updates WHERE board_type = :board");
    $stmt->execute([':board' => $boardType]);
    $result = $stmt->fetch();

    if ($result) {
        $version = (int)$result['version'];
    } else {
        // Fallback: If tracking table hasn't been updated yet, 
        // return a "calculated" version based on counts/max_ids for safety.
        // This ensures the system works even before manual triggers are added everywhere.
        $calcVersionSql = "
            (SELECT COUNT(*) FROM mcqs) + 
            (SELECT IFNULL(MAX(mcq_id), 0) FROM mcqs) +
            (SELECT COUNT(*) FROM notes) +
            (SELECT IFNULL(MAX(id), 0) FROM notes) +
            (SELECT COUNT(*) FROM flashcards)
        ";
        $calcStmt = $pdo->query($calcVersionSql);
        $version = (int)$calcStmt->fetchColumn();
        
        // Seed the table for future use
        $pdo->prepare("INSERT IGNORE INTO app_content_updates (board_type) VALUES (?)")->execute([$boardType]);
    }

    echo json_encode([
        'status' => 'success',
        'version' => $version
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
