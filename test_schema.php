<?php
require 'c:/xampp/htdocs/veeru/config/db.php';
$stmt = $pdo->query("DESCRIBE class_updates");
$schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($schema);
