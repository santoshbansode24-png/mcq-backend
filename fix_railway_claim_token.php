<?php
require_once 'backend/config/db.php';

header('Content-Type: text/plain');

echo "=== FIXING RAILWAY DATABASE SCHEMA ===\n";

try {
    // 1. Add claim_token column if missing
    $pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN claim_token VARCHAR(64) NULL AFTER progress");
    echo "✅ SUCCESS: Added 'claim_token' column to pdf_study_jobs table!\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "ℹ️ INFO: 'claim_token' column already exists.\n";
    } else {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

try {
    // 2. Add total_chunks and last_processed_chunk if missing
    @$pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN total_chunks INT DEFAULT 1 AFTER progress");
    @$pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN last_processed_chunk INT DEFAULT 0 AFTER total_chunks");
    echo "✅ SUCCESS: Verified total_chunks and last_processed_chunk columns.\n";
} catch (Exception $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

echo "=== DONE ===\n";
