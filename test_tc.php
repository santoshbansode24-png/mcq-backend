<?php
require 'config/db.php';
$stmt = $pdo->query('SHOW CREATE TABLE teacher_classes');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
