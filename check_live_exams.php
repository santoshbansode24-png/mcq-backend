<?php
require_once 'config/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE live_exams");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
