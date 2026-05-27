<?php
$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->prepare("SELECT job_id, file_name, status, progress, error_message, updated_at FROM pdf_study_jobs WHERE job_id = ?");
    $stmt->execute([130]);
    $job = $stmt->fetch();
    
    echo "Job #130: {$job['file_name']}\n";
    echo "  Status: {$job['status']} | Progress: {$job['progress']}%\n";
    echo "  Error: " . ($job['error_message'] ?? 'None') . "\n";
    echo "  Updated: {$job['updated_at']}\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
