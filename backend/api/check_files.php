<?php
require_once '../config/db.php';
$stmt = $pdo->query("SELECT note_id, title, file_path FROM notes WHERE file_path IS NOT NULL AND note_type = 'pdf'");
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];
$base_dir = dirname(__DIR__) . '/';

foreach ($notes as $note) {
    $backend_path = dirname(__DIR__) . '/' . $note['file_path'];
    $root_path = '/app/' . $note['file_path'];
    
    $exists_backend = file_exists($backend_path);
    $exists_root = file_exists($root_path);
    $exists = $exists_backend || $exists_root;
    $found_in = $exists_backend ? 'BACKEND' : ($exists_root ? 'ROOT' : 'NONE');

    $results[] = [
        'note_id' => $note['note_id'],
        'title' => $note['title'],
        'path' => $note['file_path'],
        'exists' => $exists ? 'YES' : 'NO',
        'found_in' => $found_in,
        'size' => $exists ? ($exists_backend ? filesize($backend_path) : filesize($root_path)) : 0
    ];
}

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
