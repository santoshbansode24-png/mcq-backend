<?php
require_once '../config/db.php';
$stmt = $pdo->query("SELECT * FROM notes WHERE file_path LIKE 'http%'");
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($notes, JSON_PRETTY_PRINT);
?>
