<?php
require_once '../config/db.php';
$stmt = $pdo->query("SELECT note_id, title, file_path FROM notes WHERE file_path IS NOT NULL AND note_type = 'pdf'");
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];
$base_dir = dirname(__DIR__) . '/';

foreach ($notes as $note) {
    $full_path = $base_dir . $note['file_path'];
    $exists = file_exists($full_path);
    $results[] = [
        'note_id' => $note['note_id'],
        'title' => $note['title'],
        'path' => $note['file_path'],
        'exists' => $exists ? 'YES' : 'NO',
        'size' => $exists ? filesize($full_path) : 0
    ];
}

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
