<?php
require '../config/db.php';
$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) echo $c['Field'] . "\n";
?>
