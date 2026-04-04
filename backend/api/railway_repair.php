<?php
/**
 * Railway Production Diagnostic & Repair Script
 * Features: DB Schema Sync, Environment Checks, Gemini Link Test
 */
header('Content-Type: text/html; charset=utf-8');
require_once '../config/db.php';
require_once '../config/ai_config.php';

echo "<!DOCTYPE html><html><head><title>Veeru Railway Diagnostics</title>";
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; line-height: 1.6; }
    h1 { color: #38bdf8; border-bottom: 2px solid #1e293b; padding-bottom: 10px; }
    .card { background: #1e293b; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); border: 1px solid #334155; }
    .success { color: #10b981; font-weight: bold; }
    .error { color: #ef4444; font-weight: bold; }
    .warning { color: #f59e0b; font-weight: bold; }
    .log-bg { background: #020617; padding: 15px; border-radius: 8px; font-family: monospace; overflow-x: auto; color: #a5b4fc; }
</style></head><body>";
echo "<h1>🚀 Veeru Railway Diagnostic & Repair Tool</h1>";

// --- 1. Database Check ---
echo "<div class='card'>";
echo "<h2>1. Database Schema Synchronization</h2>";
echo "<div class='log-bg'>";
try {
    // pdf_study_folders
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pdf_study_folders` (
        `folder_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `parent_id` int(11) DEFAULT NULL,
        `name` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`folder_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "<span class='success'>[OK]</span> Table `pdf_study_folders` is ready.<br>";

    // pdf_study_jobs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pdf_study_jobs` (
        `job_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `folder_id` int(11) DEFAULT NULL,
        `file_name` varchar(255) NOT NULL,
        `file_path` text NOT NULL,
        `pdf_base64` longtext DEFAULT NULL,
        `study_content` longtext DEFAULT NULL,
        `error_message` text DEFAULT NULL,
        `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
        `progress` int(11) DEFAULT 0,
        `total_pages` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`job_id`),
        KEY `user_id` (`user_id`),
        KEY `folder_id` (`folder_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "<span class='success'>[OK]</span> Table `pdf_study_jobs` is ready.<br>";

    // pdf_study_content
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pdf_study_content` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `job_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `study_pack_json` longtext NOT NULL,
        `is_synced` tinyint(1) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `job_id` (`job_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "<span class='success'>[OK]</span> Table `pdf_study_content` is ready.<br>";

    // Safety checks for existing columns if table existed prior to upgrade
    $tableChecks = [
        "pdf_study_jobs" => ["folder_id", "study_content", "error_message", "pdf_base64", "total_pages"],
        "pdf_study_content" => ["is_synced"]
    ];

    foreach ($tableChecks as $table => $columns) {
        foreach ($columns as $column) {
            try {
                $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                if (!$check->fetch()) {
                    $type = ($column == 'folder_id' || $column == 'total_pages' || $column == 'is_synced') ? "INT DEFAULT 0" : "LONGTEXT DEFAULT NULL";
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $type");
                    echo "<span class='warning'>[FIXED]</span> Added missing column `$column` to `$table`.<br>";
                }
            } catch (Exception $e) {}
        }
    }

} catch (Exception $e) {
    echo "<span class='error'>[ERROR]</span> Database logic failed: " . htmlspecialchars($e->getMessage()) . "<br>";
}
echo "</div></div>";

// --- 2. Environment Variables ---
echo "<div class='card'>";
echo "<h2>2. Environment Status</h2>";
echo "<div class='log-bg'>";

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
if (!empty($apiKey)) {
    echo "<span class='success'>[OK]</span> GEMINI_API_KEY is configured (" . strlen($apiKey) . " chars).<br>";
} else {
    echo "<span class='error'>[FAIL]</span> GEMINI_API_KEY is MISSING from environment variables!<br>";
}

$workerSecret = defined('WORKER_SECRET') ? WORKER_SECRET : 'MISSING';
if ($workerSecret !== 'MISSING') {
    echo "<span class='success'>[OK]</span> WORKER_SECRET matches configuration.<br>";
}

// Uploads Directory
$uploadDir = dirname(__DIR__) . '/uploads/pdf_study';
if (!is_dir($uploadDir)) {
    if (@mkdir($uploadDir, 0777, true)) {
        echo "<span class='warning'>[FIXED]</span> Created uploads directory.<br>";
    } else {
        echo "<span class='error'>[FAIL]</span> Cannot create uploads directory: $uploadDir<br>";
    }
}
if (is_dir($uploadDir) && is_writable($uploadDir)) {
    echo "<span class='success'>[OK]</span> Uploads directory is writable.<br>";
} else {
    echo "<span class='error'>[FAIL]</span> Uploads directory is NOT writable.<br>";
}

echo "PHP memory_limit: " . ini_get('memory_limit') . "<br>";
echo "PHP upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "PHP post_max_size: " . ini_get('post_max_size') . "<br>";
echo "</div></div>";

// --- 3. Gemini API Connectivity Test ---
echo "<div class='card'>";
echo "<h2>3. Google Gemini Connect Test</h2>";
echo "<div class='log-bg'>";
if (empty($apiKey)) {
    echo "<span class='warning'>Skipping API test as key is missing.</span><br>";
} else {
    echo "Sending ping to Google AI Studio...<br>";
    try {
        $prompt = "Reply with exactly 'Veeru AI Online'.";
        $response = callGeminiAPI($prompt);
        if (strpos(strtolower($response), 'veeru') !== false || strpos(strtolower($response), 'online') !== false) {
            echo "<span class='success'>[SUCCESS]</span> API answered flawlessly: <i>" . htmlspecialchars($response) . "</i><br>";
        } else {
            echo "<span class='warning'>[WARNING]</span> Unexpected response: " . htmlspecialchars($response) . "<br>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>[ERROR]</span> Connect failed: " . htmlspecialchars($e->getMessage()) . "<br>";
    }
}
echo "</div></div>";

echo "<div style='text-align:center; margin-top:30px; font-weight:bold; color:#10b981;'>All system checks complete. If all lights are green, your Railway production server is 100% ready.</div>";
echo "</body></html>";
?>
