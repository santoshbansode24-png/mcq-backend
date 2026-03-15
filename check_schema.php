<?php
require_once 'backend/config/db.php';
$stmt = $pdo->query("DESCRIBE study_tasks");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
