<?php
/**
 * AI Service Debugger - Railway Diagnostic Tool
 */

// 1. Basic Health
header("Content-Type: text/html; charset=UTF-8");
echo "<h2>🚀 AI Service Debugger (Railway)</h2>";

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/AiUsageManager.php';
require_once __DIR__ . '/AiTaskManager.php';

echo "<h3>✅ PHP Version: " . phpversion() . "</h3>";

// 2. Database Connection Check
try {
    global $pdo;
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color:green'>✅ Database Connected Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database Connection Failed: " . $e->getMessage() . "</p>";
}

// 3. Table Check & Patch
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ai_tasks'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "<p style='color:green'>✅ ai_tasks table exists!</p>";
        // Patch wrong columns if they exist
        try {
            // Check for 'type' instead of 'task_type'
            $colStmt = $pdo->query("SHOW COLUMNS FROM ai_tasks LIKE 'type'");
            if ($colStmt->fetch()) {
                $pdo->exec("ALTER TABLE ai_tasks CHANGE `type` `task_type` VARCHAR(50) NOT NULL");
                echo "<p style='color:green'>✅ Patched 'type' to 'task_type'!</p>";
            }
            
            // Check for 'result' instead of 'result_data'
            $colStmt2 = $pdo->query("SHOW COLUMNS FROM ai_tasks LIKE 'result'");
            if ($colStmt2->fetch()) {
                $pdo->exec("ALTER TABLE ai_tasks CHANGE `result` `result_data` MEDIUMTEXT");
                echo "<p style='color:green'>✅ Patched 'result' to 'result_data'!</p>";
            }

            // Check for 'request_payload'
            $colStmt3 = $pdo->query("SHOW COLUMNS FROM ai_tasks LIKE 'request_payload'");
            if (!$colStmt3->fetch()) {
                $pdo->exec("ALTER TABLE ai_tasks ADD COLUMN `request_payload` TEXT AFTER `status`");
                echo "<p style='color:green'>✅ Added missing 'request_payload' column!</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:orange'>⚠️ Could not patch table: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:orange'>⚠️ ai_tasks table missing. Attempting to create it...</p>";
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_type VARCHAR(50) NOT NULL,
            status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
            request_payload TEXT,
            result_data MEDIUMTEXT,
            error_message TEXT,
            progress INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        echo "<p style='color:green'>✅ ai_tasks table created successfully with correct schema!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error checking/creating tables: " . $e->getMessage() . "</p>";
}

// 4. Gemini API Key Check
$apiKey = getenv('GEMINI_API_KEY');
if ($apiKey) {
    echo "<p style='color:green'>✅ GEMINI_API_KEY is SET (Starts with: " . substr($apiKey, 0, 5) . "...)</p>";
} else {
    echo "<p style='color:red'>❌ GEMINI_API_KEY NOT FOUND in environment.</p>";
}

// 5. Upload Folder Check
$uploadDir = '../uploads/temp_tasks/';
if (is_dir($uploadDir)) {
    echo "<p style='color:green'>✅ Upload folder exists: $uploadDir</p>";
} else {
    echo "<p style='color:orange'>⚠️ Upload folder missing. Attempting to create...</p>";
    if (mkdir($uploadDir, 0777, true)) {
        echo "<p style='color:green'>✅ Created upload folder successfully.</p>";
    } else {
        echo "<p style='color:red'>❌ Failed to create upload folder.</p>";
    }
}

// Ensure autoloader runs in debug script to test pathing
$autoloadChecked = [];
$autoloadFound = false;

$paths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/backend/vendor/autoload.php'
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadFound = $path;
        break;
    }
    $autoloadChecked[] = $path;
}

// 6. Composer Library Check
echo "<h3>✅ Library Check:</h3>";
if ($autoloadFound) {
    echo "<p style='color:green'>✅ Autoloader found at: $autoloadFound</p>";
} else {
    echo "<p style='color:red'>❌ Autoloader NOT FOUND. Checked paths:<br>" . implode("<br>", $autoloadChecked) . "</p>";
}

if (class_exists('Smalot\PdfParser\Parser')) {
    echo "<p style='color:green'>✅ PDF Parser Library Loaded successfully.</p>";
} else {
    echo "<p style='color:red'>❌ PDF Parser Library MISSING (Class Smalot\PdfParser\Parser not found).</p>";
}


echo "<hr><i>Debugger finished at " . date('Y-m-d H:i:s') . "</i>";
?>
