<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE class_updates");
print_r($stmt->fetch());
?>
