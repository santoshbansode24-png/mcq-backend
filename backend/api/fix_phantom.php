<?php
require_once '../config/db.php';
$stmt = $pdo->prepare("DELETE FROM pdf_study_folders WHERE name = 'Root Document' OR name = 'Knowledge Vault'");
$stmt->execute();
echo 'Deleted ' . $stmt->rowCount() . ' rows.';
?>
