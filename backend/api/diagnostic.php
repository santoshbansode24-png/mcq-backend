<?php
header('Content-Type: text/plain');
require_once '../config/db.php';
require_once '../config/ai_config.php';

echo "🔍 VEERU LENS DIAGNOSTIC\n";
echo "===============================\n\n";

// 1. PHP Limits
echo "📋 PHP CONFIGURATION:\n";
echo "- upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "- post_max_size: " . ini_get('post_max_size') . "\n";
echo "- memory_limit: " . ini_get('memory_limit') . "\n";
echo "- max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "\n";

// 2. Database Checks
echo "🗄️ DATABASE CHECKS:\n";
try {
    $packetStmt = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
    $packetVar = $packetStmt->fetch();
    echo "- max_allowed_packet: " . ($packetVar['Value'] / 1024 / 1024) . " MB\n";
    
    $tables = ['pdf_study_jobs', 'pdf_study_content'];
    foreach ($tables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() > 0) {
            echo "- Table '$table': ✅ EXISTS\n";
            $cols = $pdo->query("SHOW COLUMNS FROM $table");
            while ($col = $cols->fetch()) {
                if ($col['Field'] == 'pdf_base64') echo "  - pdf_base64 column: ✅ EXISTS (" . $col['Type'] . ")\n";
                if ($col['Field'] == 'study_pack_json') echo "  - study_pack_json column: ✅ EXISTS (" . $col['Type'] . ")\n";
            }
        } else {
            echo "- Table '$table': ❌ MISSING\n";
        }
    }
} catch (Exception $e) {
    echo "- DB Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Job Status
echo "📊 JOB STATISTICS:\n";
try {
    $stats = $pdo->query("SELECT status, COUNT(*) as count FROM pdf_study_jobs GROUP BY status")->fetchAll();
    if (empty($stats)) {
        echo "- No jobs found in pdf_study_jobs.\n";
    } else {
        foreach ($stats as $stat) {
            echo "- " . strtoupper($stat['status']) . ": " . $stat['count'] . "\n";
        }
    }
    
    echo "\n⚠️ RECENT FAILURES:\n";
    $fails = $pdo->query("SELECT job_id, file_name, error_message, updated_at FROM pdf_study_jobs WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 5")->fetchAll();
    foreach ($fails as $fail) {
        echo "- Job #{$fail['job_id']} ({$fail['file_name']}): {$fail['error_message']} [{$fail['updated_at']}]\n";
    }
} catch (Exception $e) {
    echo "- Job check failed: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. AI Connectivity (Quick Ping)
echo "🤖 AI CONNECTIVITY:\n";
echo "- API Key: " . (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY) ? "✅ Configured (" . substr(GEMINI_API_KEY, 0, 8) . "...)" : "❌ MISSING") . "\n";

try {
    // Quick test with gemini-2.5-flash
    $payload = [
        'contents' => [['parts' => [['text' => 'Ping']]]],
        'generationConfig' => ['maxOutputTokens' => 5]
    ];
    $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code === 200) {
        echo "- Ping Gemini (2.5 Flash): ✅ SUCCESS\n";
    } else {
        $err = json_decode($res, true);
        echo "- Ping Gemini (2.5 Flash): ❌ FAILED (Code $code). Error: " . ($err['error']['message'] ?? substr($res, 0, 100)) . "\n";
    }
} catch (Exception $e) {
    echo "- AI Connectivity check failed: " . $e->getMessage() . "\n";
}

echo "\n--- END OF DIAGNOSTIC ---\n";
