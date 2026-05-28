<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT * FROM teacher_classes LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
