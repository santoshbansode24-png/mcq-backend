<?php
require 'backend/config/db.php';
$stmt = $pdo->query("DESCRIBE pdf_study_jobs");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
