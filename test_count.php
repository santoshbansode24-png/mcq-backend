<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT COUNT(*) FROM mcqs WHERE chapter_id IN (15,77,78,79)");
print_r($stmt->fetch());
?>
