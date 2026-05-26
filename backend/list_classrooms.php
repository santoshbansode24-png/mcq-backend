<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query("SELECT * FROM classrooms");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);
?>
