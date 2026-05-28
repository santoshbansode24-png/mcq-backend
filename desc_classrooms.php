<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE classrooms");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
