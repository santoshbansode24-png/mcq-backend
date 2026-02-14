<?php
header('Content-Type: text/plain');

echo "=== Upload Directory Test ===\n\n";

$upload_dir = __DIR__ . '/../uploads/notes';
echo "Upload Directory: $upload_dir\n";
echo "Directory Exists: " . (is_dir($upload_dir) ? 'YES' : 'NO') . "\n";
echo "Directory Writable: " . (is_writable($upload_dir) ? 'YES' : 'NO') . "\n\n";

if (is_dir($upload_dir)) {
    echo "Files in directory:\n";
    $files = scandir($upload_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $full_path = $upload_dir . '/' . $file;
            echo "  - $file (" . filesize($full_path) . " bytes)\n";
        }
    }
    if (count($files) <= 2) {
        echo "  (empty)\n";
    }
} else {
    echo "Directory does not exist!\n";
    echo "Attempting to create...\n";
    if (mkdir($upload_dir, 0777, true)) {
        echo "Created successfully!\n";
    } else {
        echo "Failed to create directory!\n";
    }
}

echo "\n=== Database Check ===\n\n";
require_once '../config/db.php';

$stmt = $pdo->query("SELECT note_id, title, note_type, file_path FROM notes WHERE note_type = 'pdf' ORDER BY created_at DESC LIMIT 5");
$notes = $stmt->fetchAll();

echo "Recent PDF notes in database:\n";
foreach ($notes as $note) {
    echo "  - ID: {$note['note_id']}, Title: {$note['title']}\n";
    echo "    Path: {$note['file_path']}\n";
    if (!empty($note['file_path']) && strpos($note['file_path'], 'http') !== 0) {
        $file_exists = file_exists(__DIR__ . '/../' . $note['file_path']);
        echo "    File Exists: " . ($file_exists ? 'YES' : 'NO') . "\n";
    }
    echo "\n";
}
?>
