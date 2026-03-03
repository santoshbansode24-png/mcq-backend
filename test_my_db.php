<?php
require 'backend/config/db.php';
$stmt = $pdo->query('SELECT * FROM classes');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
