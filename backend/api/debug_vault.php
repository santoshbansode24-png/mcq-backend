<?php
/**
 * VAULT DIAGNOSTIC - Railway Safe Version
 */
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== RAILWAY VAULT DIAGNOSTIC ===\n\n";

// Use absolute paths to be safe on Railway
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/ai_config.php';

// Step 1: Check Database
echo "1. CHECKING DB CONNECTION:\n";
try {
    $pdo->query("SELECT 1");
    echo "   ✅ OK - Database connected\n\n";
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Step 2: Check API Key
echo "2. GEMINI API KEY:\n";
try {
    if (defined('GEMINI_API_KEY') && strlen(GEMINI_API_KEY) > 10) {
        echo "   ✅ Key loaded: " . substr(GEMINI_API_KEY, 0, 12) . "...\n";
        echo "   📡 URL: " . GEMINI_API_URL . "\n\n";
    } else {
        echo "   ❌ ERROR - GEMINI_API_KEY is not defined!\n";
        $envKey = getenv('GEMINI_API_KEY');
        if ($envKey) {
            echo "   Found in ENV: " . substr($envKey, 0, 12) . "...\n\n";
        } else {
            echo "   Not in ENV either. Check Railway Variables!\n\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Step 3: Check Recent Jobs
echo "3. RECENT JOBS (Last 5):\n";
try {
    $stmt = $pdo->query("SELECT job_id, user_id, file_name, status, progress, LENGTH(pdf_base64) as base64_len, file_path, error_message, created_at FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 5");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($jobs)) {
        echo "   No jobs in database\n\n";
    } else {
        foreach ($jobs as $j) {
            echo "   Job #{$j['job_id']} (User {$j['user_id']}): {$j['file_name']}\n";
            echo "   Time: {$j['created_at']} | Base64 Length: {$j['base64_len']} bytes\n";
            echo "   Status={$j['status']} | Progress={$j['progress']}%\n";
            if ($j['error_message']) echo "   ERROR: {$j['error_message']}\n";
            echo "   -----------------------------------\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "   ❌ DB Error: " . $e->getMessage() . "\n\n";
}

// Step 4: Test Gemini API
echo "4. GEMINI API TEST (Quick check):\n";
try {
    if (function_exists('callGeminiAPI')) {
        $result = callGeminiAPI("Reply with one word: OK", ['maxOutputTokens' => 5]);
        echo "   ✅ SUCCESS - Response: $result\n\n";
    } else {
        echo "   ❌ ERROR - callGeminiAPI function not found!\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ FAILED: " . $e->getMessage() . "\n\n";
}

echo "=== DONE ===\n";
?>
