<?php
/**
 * AI Service Debugger - Railway Diagnostic Tool
 */

// 1. Basic Health
header("Content-Type: text/html; charset=UTF-8");
echo "<h2>🚀 AI Service Debugger (Railway)</h2>";

require_once '../config/db.php';
require_once 'AiUsageManager.php';
require_once 'AiTaskManager.php';

echo "<h3>✅ PHP Version: " . phpversion() . "</h3>";

// 2. Database Connection Check
try {
    global $pdo;
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color:green'>✅ Database Connected Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database Connection Failed: " . $e->getMessage() . "</p>";
}

// 3. Table Check
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ai_tasks'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "<p style='color:green'>✅ ai_tasks table exists!</p>";
    } else {
        echo "<p style='color:orange'>⚠️ ai_tasks table missing.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error checking tables: " . $e->getMessage() . "</p>";
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

// 6. Composer Library Check
echo "<h3>✅ Library Check:</h3>";
if (class_exists('Smalot\PdfParser\Parser')) {
    echo "<p style='color:green'>✅ PDF Parser Library Loaded.</p>";
} else {
    echo "<p style='color:red'>❌ PDF Parser Library MISSING (Check vendor/autoload.php).</p>";
}

echo "<hr><i>Debugger finished at " . date('Y-m-d H:i:s') . "</i>";
?>
