<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT * FROM notifications WHERE message LIKE '%JSON_PAYLOAD%' ORDER BY notification_id DESC LIMIT 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
