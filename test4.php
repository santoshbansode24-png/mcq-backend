<?php
require 'c:/xampp/htdocs/veeru/config/db.php';
$stmt = $pdo->prepare("SELECT cu.*, u.name as teacher_name FROM class_updates cu JOIN users u ON cu.teacher_id = u.user_id WHERE cu.class_id = 1");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
