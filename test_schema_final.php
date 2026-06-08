<?php
require_once 'backend/config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM class_updates LIKE 'update_type'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
