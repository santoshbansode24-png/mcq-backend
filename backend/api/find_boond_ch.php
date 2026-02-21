<?php
require_once '../config/db.php';
$stmt = $pdo->query("SELECT * FROM chapters WHERE chapter_name LIKE '%BOOND%'");
$chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($chapters, JSON_PRETTY_PRINT);
?>
