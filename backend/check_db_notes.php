<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT note_id, chapter_id, title, file_path FROM notes WHERE note_type = 'pdf' ORDER BY note_id DESC LIMIT 10");
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($notes, JSON_PRETTY_PRINT);
?>
