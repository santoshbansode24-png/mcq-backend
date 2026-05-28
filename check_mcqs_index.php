<?php
require_once 'config/db.php';
$stmt = $pdo->query("SHOW INDEX FROM mcqs");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
