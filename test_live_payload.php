<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT payload, message FROM class_updates WHERE update_type IN ('live_class', 'live_exam') ORDER BY update_id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
