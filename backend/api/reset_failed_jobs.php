<?php
require_once '../config/db.php';
$stmt = $pdo->prepare("UPDATE pdf_study_jobs SET status = 'pending', error_message = NULL, progress = 0 WHERE status = 'failed'");
$stmt->execute();
echo 'Reset ' . $stmt->rowCount() . ' failed job(s) back to pending.';
?>
