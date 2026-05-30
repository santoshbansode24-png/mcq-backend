<?php
require 'config/db.php';
$stmt = $pdo->query('SHOW CREATE TABLE messages');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
