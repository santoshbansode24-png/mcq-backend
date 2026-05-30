<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM class_updates");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
?>
