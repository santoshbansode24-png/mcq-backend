<?php
require_once '../config/db.php';

try {
    // Modify column to support 2 decimal places (e.g., 0.25)
    $pdo->exec("ALTER TABLE study_plans MODIFY COLUMN target_hours_per_day DECIMAL(4,2) DEFAULT 1.0");
    echo json_encode(['status' => 'success', 'message' => 'Schema updated: target_hours_per_day is now DECIMAL(4,2)']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
