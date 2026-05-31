<?php
require 'c:/xampp/htdocs/veeru/config/db.php';
$stmt = $pdo->prepare("SELECT payload FROM class_updates WHERE update_id = 5");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
