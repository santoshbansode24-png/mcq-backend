<?php
require 'config/db.php';
print_r($pdo->query('SHOW COLUMNS FROM class_updates')->fetchAll(PDO::FETCH_ASSOC));
?>
