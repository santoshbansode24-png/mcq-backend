<?php
// migrate_board.php
require_once 'config/db.php';

echo "Starting migration from STATE_MARATHI to CBSE...\n";

try {
    // 1. Check current counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes WHERE board_type = 'STATE_MARATHI'");
    $countMarathi = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes WHERE board_type = 'CBSE'");
    $countCBSE = $stmt->fetchColumn();

    echo "Before: STATE_MARATHI = $countMarathi, CBSE = $countCBSE\n";

    if ($countMarathi == 0) {
        echo "No classes found in STATE_MARATHI. Nothing to migrate.\n";
    } else {
        // 2. Perform Migration
        $sql = "UPDATE classes SET board_type = 'CBSE' WHERE board_type = 'STATE_MARATHI'";
        $rows = $pdo->exec($sql);
        
        echo "Migrated $rows classes from STATE_MARATHI to CBSE.\n";
    }

    // 3. Verify
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes WHERE board_type = 'STATE_MARATHI'");
    $newCountMarathi = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes WHERE board_type = 'CBSE'");
    $newCountCBSE = $stmt->fetchColumn();

    echo "After: STATE_MARATHI = $newCountMarathi, CBSE = $newCountCBSE\n";
    
    echo "Migration Complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
