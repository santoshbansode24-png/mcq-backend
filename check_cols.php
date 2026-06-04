<?php
require_once 'backend/config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM class_updates");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SHOW COLUMNS FROM notifications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
