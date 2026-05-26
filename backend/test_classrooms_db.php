<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query("SELECT * FROM classrooms");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
