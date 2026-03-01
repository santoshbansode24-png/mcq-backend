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

// Helper: check if a column exists
function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

// Helper: check if an index exists
function indexExists($pdo, $table, $index) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

// --- Migration 1: add reset_token column
if (columnExists($pdo, 'password_reset_otps', 'reset_token')) {
    $results['add_reset_token_column'] = ['status' => 'already_exists', 'message' => 'Column already exists — skipped'];
} else {
    try {
        $pdo->exec("ALTER TABLE password_reset_otps ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL COMMENT 'Secure token issued after OTP verification'");
        $results['add_reset_token_column'] = ['status' => 'success', 'message' => 'Column added OK'];
    } catch (PDOException $e) {
        $results['add_reset_token_column'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }
}

// --- Migration 2: add token_expires_at column
if (columnExists($pdo, 'password_reset_otps', 'token_expires_at')) {
    $results['add_token_expires_at_column'] = ['status' => 'already_exists', 'message' => 'Column already exists — skipped'];
} else {
    try {
        $pdo->exec("ALTER TABLE password_reset_otps ADD COLUMN token_expires_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Token valid for 15 minutes after OTP verification'");
        $results['add_token_expires_at_column'] = ['status' => 'success', 'message' => 'Column added OK'];
    } catch (PDOException $e) {
        $results['add_token_expires_at_column'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }
}

// --- Migration 3: add index on reset_token
if (indexExists($pdo, 'password_reset_otps', 'idx_reset_token')) {
    $results['add_reset_token_index'] = ['status' => 'already_exists', 'message' => 'Index already exists — skipped'];
} else {
    try {
        $pdo->exec("ALTER TABLE password_reset_otps ADD INDEX idx_reset_token (reset_token)");
        $results['add_reset_token_index'] = ['status' => 'success', 'message' => 'Index added OK'];
    } catch (PDOException $e) {
        $results['add_reset_token_index'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
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
