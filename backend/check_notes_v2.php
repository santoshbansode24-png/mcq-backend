<?php
require_once '../config/db.php';
$stmt = $pdo->query("SELECT note_id, chapter_id, title, file_path, created_at FROM notes ORDER BY note_id DESC LIMIT 5");
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($notes, JSON_PRETTY_PRINT);
?>
