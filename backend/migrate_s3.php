<?php
include 'config/db.php';

// Check if running from CLI or if secret key matches (basic security)
// For now, just run it.

echo "Starting Migration: Update 'notes' table for S3 support...\n";

try {
    // 1. Alter file_path column to TEXT to support long S3 URLs
    $sql = "ALTER TABLE notes MODIFY COLUMN file_path TEXT";
    $conn->exec($sql);
    echo "[SUCCESS] Adjusted 'file_path' column to TEXT.\n";

} catch(PDOException $e) {
    echo "[INFO] Migration might have already run or failed: " . $e->getMessage() . "\n";
}

echo "Migration Completed.\n";
?>
