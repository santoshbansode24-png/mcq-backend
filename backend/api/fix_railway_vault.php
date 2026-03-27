<?php
/**
 * Universal Database Fixer for Railway (Safe version)
 */
require_once '../config/db.php';

echo "<pre>";
echo "🔄 Starting Database Upgrade for PDF Vault...\n";

try {
    // 1. Create Folders Table
    $sql1 = "CREATE TABLE IF NOT EXISTS `pdf_study_folders` (
        `folder_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `parent_id` int(11) DEFAULT NULL,
        `name` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`folder_id`),
        KEY `user_id` (`user_id`),
        KEY `parent_id` (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql1);
    echo "✅ Table 'pdf_study_folders' created or verified.\n";

    // 2. Add folder_id column
    try {
        $pdo->exec("ALTER TABLE `pdf_study_jobs` ADD COLUMN `folder_id` INT DEFAULT NULL AFTER `user_id` ");
        echo "✅ Column 'folder_id' added.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
             echo "ℹ️ Column 'folder_id' already existed.\n";
        } else {
             echo "⚠️ Column Warning: " . $e->getMessage() . "\n";
        }
    }

    // 3. Add study_content column
    try {
        $pdo->exec("ALTER TABLE `pdf_study_jobs` ADD COLUMN `study_content` LONGTEXT DEFAULT NULL AFTER `file_path` ");
        echo "✅ Column 'study_content' added.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
             echo "ℹ️ Column 'study_content' already existed.\n";
        } else {
             echo "⚠️ Column Warning: " . $e->getMessage() . "\n";
        }
    }

    // 4. Add error_message column
    try {
        $pdo->exec("ALTER TABLE `pdf_study_jobs` ADD COLUMN `error_message` TEXT DEFAULT NULL AFTER `study_content` ");
        echo "✅ Column 'error_message' added.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
             echo "ℹ️ Column 'error_message' already existed.\n";
        } else {
             echo "⚠️ Column Warning: " . $e->getMessage() . "\n";
        }
    }

    echo "\n🚀 MISSION SUCCESS: Your Knowledge Vault is now fully unlocked!";
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage();
}
echo "</pre>";
?>
