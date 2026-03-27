<?php
/**
 * VAULT DIAGNOSTIC - Railway Safe Version
 */
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== RAILWAY VAULT DIAGNOSTIC ===\n\n";

// Step 1: Check includes
echo "1. CHECKING DB CONNECTION:\n";
try {
    require_once '../config/db.php';
    $pdo->query("SELECT 1");
    echo "   OK - Database connected\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
    die();
}

// Step 2: Check API Key  
echo "2. CHECKING API KEY:\n";
try {
    require_once '../config/ai_config.php';
    if (defined('GEMINI_API_KEY') && strlen(GEMINI_API_KEY) > 10) {
        echo "   OK - Key loaded: " . substr(GEMINI_API_KEY, 0, 12) . "...\n\n";
    } else {
        echo "   ERROR - GEMINI_API_KEY is not defined!\n";
        // Try env var directly
        $envKey = getenv('GEMINI_API_KEY');
        if ($envKey) {
            echo "   Found in ENV: " . substr($envKey, 0, 12) . "...\n\n";
        } else {
            echo "   Not in ENV either. Check Railway Variables!\n\n";
        }
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Step 3: Check pending jobs
echo "3. RECENT JOBS:\n";
try {
    $stmt = $pdo->query("SELECT job_id, file_name, status, progress, error_message, created_at FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 5");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($jobs)) {
        echo "   No jobs in database\n\n";
    } else {
        foreach ($jobs as $j) {
            echo "   Job #{$j['job_id']}: {$j['file_name']}\n";
            echo "   Status={$j['status']} | Progress={$j['progress']}%\n";
            if ($j['error_message']) echo "   ERROR: {$j['error_message']}\n";
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "   DB Error: " . $e->getMessage() . "\n\n";
}

// Step 4: Test Gemini API
echo "4. GEMINI API TEST:\n";
try {
    $result = callGeminiAPI("Reply with one word: OK", ['maxOutputTokens' => 5]);
    echo "   SUCCESS - Response: $result\n\n";
} catch (Exception $e) {
    echo "   FAILED: " . $e->getMessage() . "\n\n";
}

echo "=== DONE ===\n";
?>
