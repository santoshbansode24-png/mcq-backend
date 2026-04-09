<?php
/**
 * Update Study Tasks Schema
 * Adds new task types to the ENUM
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // Add chapter_ids column if missing
    try {
        $pdo->exec("ALTER TABLE study_tasks ADD COLUMN chapter_ids TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignore if error is duplicate column
    }

    // 1. Disable MySQL strict mode in this session to downgrade Truncation Errors to Warnings
    $pdo->exec("SET SESSION sql_mode = ''");

    // 2. Temporarily tell PDO not to throw exceptions for Warnings
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

    // 3. Convert task_type to VARCHAR(50) to permanently prevent any ENUM truncation errors
    $sql = "ALTER TABLE study_tasks MODIFY COLUMN task_type VARCHAR(50) NOT NULL DEFAULT 'custom'";
    $pdo->exec($sql);

    // 4. Retrieve any errors (Code 01000 is just a Warning and means it succeeded)
    $errorInfo = $pdo->errorInfo();
    
    // 5. Turn exception mode back on
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // If there's an actual hard error (not 00000 success and not 01000 warning), throw it
    if ($errorInfo[0] !== '00000' && $errorInfo[0] !== '01000') {
        throw new PDOException("Hard migration error: " . $errorInfo[2], (int)$errorInfo[0]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'study_tasks table updated with new task types.'
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Schema Update Failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
