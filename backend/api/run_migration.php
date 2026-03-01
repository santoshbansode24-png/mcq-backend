<?php
/**
 * ONE-TIME MIGRATION SCRIPT
 * Adds reset_token columns to password_reset_otps table.
 * 
 * ⚠️  DELETE THIS FILE AFTER RUNNING IT ONCE on production!
 * Access: https://api.veeruapp.in/backend/api/run_migration.php?secret=veeru_migrate_2026
 */

require_once '../config/db.php';

header('Content-Type: application/json');

// Simple secret to prevent accidental/unauthorized runs
$secret = $_GET['secret'] ?? '';
if ($secret !== 'veeru_migrate_2026') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Pass ?secret=veeru_migrate_2026']);
    exit;
}

$results = [];
$allOk   = true;

$migrations = [
    'add_reset_token_column' => "
        ALTER TABLE password_reset_otps
        ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL DEFAULT NULL
        COMMENT 'Secure token issued after OTP is verified'
    ",
    'add_token_expires_at_column' => "
        ALTER TABLE password_reset_otps
        ADD COLUMN IF NOT EXISTS token_expires_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Token valid for 15 minutes after OTP verification'
    ",
    'add_reset_token_index' => "
        ALTER TABLE password_reset_otps
        ADD INDEX IF NOT EXISTS idx_reset_token (reset_token)
    ",
];

foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec(trim($sql));
        $results[$name] = ['status' => 'success', 'message' => 'OK'];
    } catch (PDOException $e) {
        // "Duplicate column" means it was already added — treat as OK
        if (strpos($e->getMessage(), 'Duplicate column') !== false ||
            strpos($e->getMessage(), 'already exists') !== false) {
            $results[$name] = ['status' => 'already_exists', 'message' => 'Column/index already exists — skipped'];
        } else {
            $results[$name] = ['status' => 'error', 'message' => $e->getMessage()];
            $allOk = false;
        }
    }
}

// Show final table structure
try {
    $stmt = $pdo->query("DESCRIBE password_reset_otps");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
} catch (PDOException $e) {
    $columnNames = ['error: ' . $e->getMessage()];
}

echo json_encode([
    'status'        => $allOk ? 'success' : 'partial_failure',
    'message'       => $allOk
                        ? '✅ Migration complete! DELETE this file now.'
                        : '⚠️ Some steps failed. Check results.',
    'migrations'    => $results,
    'table_columns' => $columnNames,
], JSON_PRETTY_PRINT);
?>
