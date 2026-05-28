<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE student_class_mapping");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
