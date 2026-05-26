<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM class_updates");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
