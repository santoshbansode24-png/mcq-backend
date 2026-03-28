<?php
$_GET['user_id'] = 8; // Adjust to a valid user ID, 8 was just a guess. Let's find the actual user ID from the jobs
require 'backend/config/db.php';
$stmt = $pdo->query("SELECT user_id FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 1");
$u = $stmt->fetchColumn();
echo "User ID: $u\n";

$_GET['user_id'] = $u;
ob_start();
require 'backend/api/get_pdf_study_status.php';
$out = ob_get_clean();
echo "Response Length: " . strlen($out) . "\n";
$decoded = json_decode($out, true);
if (json_last_error() === JSON_ERROR_NONE) {
    if (isset($decoded['status'])) echo "Status: " . $decoded['status'] . "\n";
    if (isset($decoded['data'])) echo "Data items: " . count($decoded['data']) . "\n";
} else {
    echo "JSON Decode Error: " . json_last_error_msg() . "\n";
    echo substr($out, 0, 500) . "\n";
}
?>
