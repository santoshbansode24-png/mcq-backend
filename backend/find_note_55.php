<?php
require_once 'config/db.php';
$stmt = $pdo->prepare("SELECT * FROM notes WHERE note_id = ?");
$stmt->execute([55]);
$note = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($note, JSON_PRETTY_PRINT);
?>
