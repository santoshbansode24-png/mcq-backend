<?php
require 'config/db.php';
$stmt = $pdo->query('SHOW COLUMNS FROM class_updates');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
