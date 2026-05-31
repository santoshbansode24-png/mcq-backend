<?php
require 'c:/xampp/htdocs/veeru/config/db.php';
$stmt = $pdo->prepare("SELECT cu.update_id, cu.class_id, cu.update_type, cu.title, cu.message, LENGTH(cu.payload) as payload_len, cu.created_at FROM class_updates cu ORDER BY cu.created_at DESC LIMIT 10");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
