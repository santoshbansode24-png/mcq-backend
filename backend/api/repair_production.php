<?php
/**
 * Production Repair Script — Permanent Fix for PDF-to-Exam
 * 
 * Instructions:
 * 1. Deploy this code to Railway.
 * 2. Visit https://api.veeruapp.in/backend/api/repair_production.php in your browser.
 * 3. It will automatically detect and fix any missing columns or stuck jobs.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== VEERU PRODUCTION REPAIR TOOL ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Ensure Table Structure is Optimized
    echo "1. Checking pdf_study_jobs table...\n";
    
    // Create table if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pdf_study_jobs` (
        `job_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(512) NOT NULL,
        `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        `progress` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add missing columns manually with single ALTERs for maximum compatibility
    $requiredCols = [
        'folder_id'      => "ALTER TABLE pdf_study_jobs ADD COLUMN folder_id INT DEFAULT NULL AFTER user_id",
        'pdf_base64'     => "ALTER TABLE pdf_study_jobs ADD COLUMN pdf_base64 LONGTEXT DEFAULT NULL AFTER file_path",
        'claim_token'    => "ALTER TABLE pdf_study_jobs ADD COLUMN claim_token VARCHAR(64) DEFAULT NULL AFTER status",
        'processed_pages'=> "ALTER TABLE pdf_study_jobs ADD COLUMN processed_pages INT DEFAULT 0 AFTER progress",
        'total_pages'    => "ALTER TABLE pdf_study_jobs ADD COLUMN total_pages INT DEFAULT 0 AFTER processed_pages",
        'error_message'  => "ALTER TABLE pdf_study_jobs ADD COLUMN error_message TEXT DEFAULT NULL AFTER total_pages",
        'updated_at'     => "ALTER TABLE pdf_study_jobs ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];

    foreach ($requiredCols as $col => $sql) {
        $check = $pdo->query("SHOW COLUMNS FROM pdf_study_jobs LIKE '$col'");
        if (!$check->fetch()) {
            echo "   [+] Adding missing column: $col... ";
            $pdo->exec($sql);
            echo "Done.\n";
        } else {
            echo "   [ok] Column $col already exists.\n";
        }
    }

    // 2. Ensure Study Content Table exists
    echo "\n2. Checking pdf_study_content table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pdf_study_content` (
        `content_id` INT AUTO_INCREMENT PRIMARY KEY,
        `job_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `study_pack_json` LONGTEXT NOT NULL,
        `is_synced` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`job_id`),
        INDEX (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "   [ok] Table verified.\n";

    // 2.2 Add Index for claim_token if missing
    echo "\n2.2 Checking pdf_study_jobs indexes...\n";
    $checkIndex = $pdo->query("SHOW INDEX FROM pdf_study_jobs WHERE Column_name = 'claim_token'");
    if (!$checkIndex->fetch()) {
        echo "   [+] Adding index for claim_token... ";
        $pdo->exec("ALTER TABLE pdf_study_jobs ADD INDEX (claim_token)");
        echo "Done.\n";
    } else {
        echo "   [ok] claim_token index exists.\n";
    }

    // 2.5 Ensure content_progress table exists (Fixes 500 error on Railway)
    echo "\n2.5 Checking content_progress table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `content_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `chapter_id` INT NOT NULL,
        `content_type` ENUM('mcq', 'flashcard') NOT NULL,
        `set_index` INT NOT NULL DEFAULT 0,
        `status` ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
        `score` INT DEFAULT 0,
        `total` INT DEFAULT 0,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_content_set` (`user_id`, `chapter_id`, `content_type`, `set_index`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "   [ok] Table verified.\n";

    // 3. Check for Stuck Jobs
    echo "\n3. Checking for stuck jobs (processing > 15 mins)...\n";
    $stuck = $pdo->query("UPDATE pdf_study_jobs SET status = 'failed', error_message = 'Session timed out' WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $count = $stuck->rowCount();
    if ($count > 0) {
        echo "   [!] Marked $count stuck jobs as failed.\n";
    } else {
        echo "   [ok] No stuck jobs found.\n";
    }

    // 4. Check Database Configuration
    echo "\n4. Checking MySQL limits...\n";
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
    $packet = $stmt->fetch();
    $sizeMb = round($packet['Value'] / (1024 * 1024), 2);
    echo "   max_allowed_packet: " . $sizeMb . " MB\n";
    if ($sizeMb < 32) {
        echo "   [!] WARNING: your database packet size is small ($sizeMb MB). Large PDFs (>10MB) might fail during upload.\n";
    }

    echo "\n🚀 DATABASE IS HEALTHY AND READY!";

} catch (Exception $e) {
    echo "\n❌ REPAIR FAILED: " . $e->getMessage();
}
?>
