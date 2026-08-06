<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== DIAGNOSTIC WORKER EXECUTION ===\n";
$forceJobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : 38;

$_GET['key'] = 'veeru_ai_worker_v2_secure_ping';
$_GET['force_job_id'] = $forceJobId;

try {
    include __DIR__ . '/pdf_worker_ai.php';
    echo "\n=== WORKER FINISHED SUCCESSFULLY ===\n";
} catch (Throwable $e) {
    echo "\n=== CATCH-ALL WORKER FATAL EXCEPTION ===\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
