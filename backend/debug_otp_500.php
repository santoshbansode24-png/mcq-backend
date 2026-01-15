<?php
// backend/debug_otp_500.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...\n";

try {
    require_once 'config/db.php';
    echo "DB Connected.\n";
} catch (Exception $e) {
    echo "DB Connection Failed: " . $e->getMessage() . "\n";
    exit;
}

global $pdo;

// Check users table
try {
    $stmt = $pdo->query("SELECT mobile FROM users LIMIT 1");
    echo "Users table check: OK\n";
} catch (PDOException $e) {
    echo "Users table check FAILED: " . $e->getMessage() . "\n";
}

// Check otp_store table
try {
    $stmt = $pdo->query("SELECT * FROM otp_store LIMIT 1");
    echo "otp_store table check: OK\n";
} catch (PDOException $e) {
    echo "otp_store table check FAILED: " . $e->getMessage() . "\n";
}

// Check insert permission / logic
try {
    echo "Attempting dry-run insert...\n";
    // We won't actually commit or we roll back
    $pdo->beginTransaction();
    $mobile = '0000000000';
    $otp = '1234';
    $pdo->prepare("DELETE FROM otp_store WHERE mobile = ?")->execute([$mobile]);
    $stmt = $pdo->prepare("INSERT INTO otp_store (mobile, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->execute([$mobile, $otp]);
    $pdo->rollBack();
    echo "Insert logic check: OK\n";
} catch (PDOException $e) {
    echo "Insert logic check FAILED: " . $e->getMessage() . "\n";
}

echo "Debug complete.\n";
?>
