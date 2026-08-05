<?php
require_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'email' OR Key_name = 'email_user_type' OR Non_unique = 0");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
