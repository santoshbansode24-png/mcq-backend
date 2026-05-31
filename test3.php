<?php
require 'c:/xampp/htdocs/veeru/config/db.php';
$stmt = $pdo->query("SELECT * FROM class_updates ORDER BY update_id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
