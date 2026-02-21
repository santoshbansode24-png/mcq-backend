<?php
require_once '../config/db.php';
$stmt = $pdo->prepare("SELECT * FROM notes WHERE chapter_id = 133");
$stmt->execute();
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($notes, JSON_PRETTY_PRINT);
?>
