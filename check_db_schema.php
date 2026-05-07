<?php
require_once 'backend/config/db.php';
$stmt = $pdo->query("DESCRIBE pdf_study_jobs");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
