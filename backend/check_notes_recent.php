<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT note_id, title, file_path, note_type FROM notes ORDER BY note_id DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
