<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE teacher_classes");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
