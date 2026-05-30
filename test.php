<?php
require 'config/db.php';
$stmt = $pdo->query('SHOW CREATE TABLE classrooms');
print_r($stmt->fetch(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query('SELECT * FROM teacher_classes');
echo "Teacher Classes:\n";
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

$stmt3 = $pdo->query('SELECT * FROM classrooms');
echo "Classrooms:\n";
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
?>
