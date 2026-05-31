<?php
require 'c:/xampp/htdocs/veeru/config/db.php';
$stmt = $pdo->query("SELECT * FROM classes");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
