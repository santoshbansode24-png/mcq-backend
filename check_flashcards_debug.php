<?php
require_once 'config/db.php';
header('Content-Type: text/plain');

echo "--- CHAPTERS WITH 'HUMAN' ---\n";
$stmt = $pdo->prepare("SELECT * FROM chapters WHERE chapter_name LIKE ?");
$stmt->execute(['%Human%']);
$chapters = $stmt->fetchAll();
print_r($chapters);

echo "\n--- ALL FLASHCARDS COUNT BY CHAPTER ---\n";
$stmt = $pdo->query("SELECT chapter_id, COUNT(*) as count FROM flashcards GROUP BY chapter_id");
print_r($stmt->fetchAll());
?>
